<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BookingPayment;
use App\Models\FinanceCashAccount;
use App\Models\FinanceCashAccountBalanceHistory;
use App\Models\FinanceCashFlow;
use App\Models\Property;
use App\Services\BookingPaymentSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceCashPositionController extends Controller
{
    private const LAST_FORECAST_MONTH = '2027-12';

    public function index(Request $request, BookingPaymentSummaryService $paymentSummaryService): View
    {
        $this->authorizeFinance($request);

        $today = today();
        $currentMonth = $today->format('Y-m');
        $forecastMonthOptions = $this->forecastMonthOptions($today);
        $historyMonthOptions = FinanceCashFlow::query()
            ->select('due_date')
            ->whereDate('due_date', '<', $today->copy()->startOfMonth()->toDateString())
            ->distinct()
            ->orderBy('due_date')
            ->pluck('due_date')
            ->map(fn ($dueDate): string => Carbon::parse($dueDate)->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();
        $historyMonthLabels = $historyMonthOptions->mapWithKeys(fn (string $month): array => [
            $month => Carbon::createFromFormat('!Y-m', $month)->locale('id')->translatedFormat('F Y'),
        ]);
        $validated = $request->validate([
            'forecast_month' => ['nullable', 'date_format:Y-m', Rule::in(array_keys($forecastMonthOptions))],
            'history_month' => ['nullable', 'date_format:Y-m', Rule::in($historyMonthOptions->all())],
            'site_plan_property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);
        $selectedForecastMonth = $validated['forecast_month'] ?? $currentMonth;
        $forecastMonthStart = Carbon::createFromFormat('!Y-m', $selectedForecastMonth)->startOfMonth();
        $forecastMonthEnd = $forecastMonthStart->copy()->endOfMonth();
        $selectedHistoryMonth = $validated['history_month'] ?? $historyMonthOptions->first();
        $historyStart = $selectedHistoryMonth !== null
            ? Carbon::createFromFormat('!Y-m', $selectedHistoryMonth)->startOfMonth()
            : null;
        $historyEnd = $historyStart?->copy()->endOfMonth();
        $historyIsArchived = $historyStart?->lt($today->copy()->startOfMonth()) ?? false;
        $properties = Property::query()->orderBy('name')->get(['id', 'name', 'site_plan_path']);
        $sitePlanPropertyId = isset($validated['site_plan_property_id'])
            ? (int) $validated['site_plan_property_id']
            : null;
        $selectedProperty = $properties->firstWhere('id', $sitePlanPropertyId);
        $sitePlanLots = $selectedProperty
            ? $selectedProperty->lots()
                ->with([
                    'block',
                    'bookings' => fn ($query) => $query
                        ->whereIn('status', ['booking', 'process', 'akad', 'finish'])
                        ->with(['customer', 'sales'])
                        ->latest('id'),
                ])
                ->orderBy('lot_number')
                ->get()
            : collect();
        $sitePlanAreas = $selectedProperty
            ? $selectedProperty->sitePlanAreas()
                ->where('site_plan_path', $selectedProperty->site_plan_path)
                ->with([
                    'lot.block',
                    'lot.bookings' => fn ($query) => $query
                        ->whereIn('status', ['booking', 'process', 'akad', 'finish'])
                        ->with(['customer', 'sales'])
                        ->latest('id'),
                ])
                ->orderBy('id')
                ->get()
            : collect();
        $historyQuery = FinanceCashFlow::query()
            ->when(
                $historyStart !== null && $historyEnd !== null,
                fn ($query) => $query
                    ->whereDate('due_date', '>=', $historyStart->toDateString())
                    ->whereDate('due_date', '<=', $historyEnd->toDateString()),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
        $historySummary = (clone $historyQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN direction = 'outcome' THEN amount ELSE 0 END), 0) as outcome_total
            ")
            ->first();
        $cashFlowHistory = (clone $historyQuery)
            ->with(['property', 'createdBy'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(15, ['*'], 'history_page')
            ->withQueryString();
        $weekOptions = collect(range(1, 4))
            ->mapWithKeys(function (int $weekNumber) use ($forecastMonthStart, $forecastMonthEnd): array {
                $start = $forecastMonthStart->copy()->addDays(($weekNumber - 1) * 7);
                $end = $weekNumber === 4 ? $forecastMonthEnd->copy() : $start->copy()->addDays(6);

                return [$weekNumber => [
                    'label' => $start->format('d/m').' – '.$end->format('d/m/Y'),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ]];
            });
        $cashFlowPlans = FinanceCashFlow::query()
            ->with(['property', 'createdBy'])
            ->whereDate('due_date', '>=', $forecastMonthStart->toDateString())
            ->whereDate('due_date', '<=', $forecastMonthEnd->toDateString())
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
        $cashFlowForecasts = $weekOptions->mapWithKeys(function (array $week, int $weekNumber) use ($cashFlowPlans): array {
            $plansInWeek = $cashFlowPlans->filter(
                fn (FinanceCashFlow $plan): bool => $plan->due_date->between($week['start'], $week['end']),
            );
            $totals = [];

            foreach (['income', 'outcome'] as $direction) {
                foreach (['confirmed', 'potential', 'forecast'] as $status) {
                    $totals[$direction.'_'.$status] = (float) $plansInWeek
                        ->where('direction', $direction)
                        ->where('status', $status)
                        ->sum('amount');
                }
            }

            return [$weekNumber => [
                ...$totals,
                'label' => $week['label'],
                'plans' => $plansInWeek,
            ]];
        });
        $bookingPayments = BookingPayment::query()
            ->where('approval_status', 'approved')
            ->with(['booking.customer', 'booking.lot.block.property'])
            ->whereBetween('paid_at', [
                ($historyStart ?? $forecastMonthStart)->copy()->startOfDay(),
                ($historyEnd ?? $forecastMonthEnd)->copy()->endOfDay(),
            ])
            ->orderByDesc('paid_at')
            ->get();
        $bookingPaymentReport = $bookingPayments->map(function (BookingPayment $payment) use ($paymentSummaryService): array {
            $summary = $paymentSummaryService->summarize($payment->booking);

            return [
                'payment' => $payment,
                'summary' => $summary,
                'remaining' => collect($summary['stages'])->sum('remaining'),
            ];
        });

        return view('keuangan.cash-position.index', [
            'cashAccounts' => FinanceCashAccount::query()->orderBy('id')->get(),
            'totalCashAccountBalance' => (float) FinanceCashAccount::query()->sum('balance'),
            'cashAccountBalanceHistory' => FinanceCashAccountBalanceHistory::query()
                ->with('changedBy')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'balance_history_page')
                ->withQueryString(),
            'cashFlowForecasts' => $cashFlowForecasts,
            'cashFlowHistory' => $cashFlowHistory,
            'historyIsArchived' => $historyIsArchived,
            'historyMonthLabels' => $historyMonthLabels,
            'historySummary' => $historySummary,
            'properties' => $properties,
            'selectedProperty' => $selectedProperty,
            'sitePlanPropertyId' => $sitePlanPropertyId,
            'sitePlanLots' => $sitePlanLots,
            'sitePlanAreas' => $sitePlanAreas,
            'selectedHistoryMonth' => $selectedHistoryMonth,
            'forecastMonthOptions' => $forecastMonthOptions,
            'selectedForecastMonth' => $selectedForecastMonth,
            'weekOptions' => $weekOptions,
            'today' => $today,
            'forecastMonthStart' => $forecastMonthStart,
            'forecastMonthEnd' => $forecastMonthEnd,
            'forecastMonthLabel' => $forecastMonthStart->copy()->locale('id')->translatedFormat('F Y'),
            'bookingPaymentReport' => $bookingPaymentReport,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:finance_cash_accounts,name'],
            'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $cashAccount = FinanceCashAccount::query()->create([
                'account_key' => 'finance-'.Str::uuid(),
                'name' => trim($validated['name']),
                'balance' => $validated['balance'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $this->recordBalanceChange(
                $cashAccount,
                null,
                $cashAccount->balance,
                $request->user()->id,
            );
        });

        return to_route('keuangan.cash-position.index')->with('success', 'Rekening berhasil ditambahkan.');
    }

    public function updateAccount(Request $request, FinanceCashAccount $cashAccount): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'balance' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        DB::transaction(function () use ($cashAccount, $validated, $request): void {
            $cashAccount = FinanceCashAccount::query()
                ->lockForUpdate()
                ->findOrFail($cashAccount->id);
            $previousBalance = $cashAccount->balance;

            $cashAccount->update([
                'balance' => $validated['balance'],
                'updated_by' => $request->user()->id,
            ]);

            $this->recordBalanceChange(
                $cashAccount,
                $previousBalance,
                $cashAccount->balance,
                $request->user()->id,
            );
        });

        return to_route('keuangan.cash-position.index')->with('success', 'Saldo rekening berhasil diperbarui.');
    }

    public function destroyAccount(Request $request, FinanceCashAccount $cashAccount): RedirectResponse
    {
        $this->authorizeFinance($request);
        $cashAccount->delete();

        return to_route('keuangan.cash-position.index')->with('success', 'Rekening berhasil dihapus.');
    }

    public function storeCashFlow(Request $request): RedirectResponse
    {
        $this->authorizeFinance($request);

        $today = today();
        $forecastMonthOptions = $this->forecastMonthOptions($today);
        $validated = $request->validate([
            'forecast_month' => ['required', 'date_format:Y-m', Rule::in(array_keys($forecastMonthOptions))],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:150'],
            'direction' => ['required', Rule::in(['income', 'outcome'])],
            'status' => ['required', Rule::in(['confirmed', 'potential', 'forecast'])],
            'due_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.self::LAST_FORECAST_MONTH.'-31',
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
        ], [
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum hari ini.',
            'due_date.before_or_equal' => 'Tanggal jatuh tempo tidak boleh setelah Desember 2027.',
        ]);

        $selectedForecastMonth = $validated['forecast_month'];
        $forecastMonthStart = Carbon::createFromFormat('!Y-m', $selectedForecastMonth)->startOfMonth();
        $forecastMonthEnd = $forecastMonthStart->copy()->endOfMonth();
        $minimumDueDate = $selectedForecastMonth === $today->format('Y-m')
            ? $today
            : $forecastMonthStart;
        $dueDate = Carbon::createFromFormat('!Y-m-d', $validated['due_date']);

        if ($dueDate->lt($minimumDueDate) || $dueDate->gt($forecastMonthEnd)) {
            throw ValidationException::withMessages([
                'due_date' => 'Tanggal jatuh tempo harus berada pada bulan yang dipilih dan tidak boleh sudah lewat.',
            ]);
        }

        unset($validated['forecast_month']);
        FinanceCashFlow::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return to_route('keuangan.cash-position.index', [
            'forecast_month' => $selectedForecastMonth,
        ])->with('success', 'Rencana pemasukan/pengeluaran berhasil ditambahkan.');
    }

    public function destroyCashFlow(Request $request, FinanceCashFlow $cashFlow): RedirectResponse
    {
        $this->authorizeFinance($request);
        $dueMonth = $cashFlow->due_date->format('Y-m');
        $cashFlow->delete();

        $period = $cashFlow->due_date->lt(today()->startOfMonth())
            ? ['history_month' => $dueMonth]
            : ['forecast_month' => $dueMonth];

        return to_route('keuangan.cash-position.index', $period)
            ->with('success', 'Rencana arus kas berhasil dihapus.');
    }

    /**
     * @return array<string, string>
     */
    private function forecastMonthOptions(\DateTimeInterface $today): array
    {
        $month = Carbon::createFromFormat('!Y-m', $today->format('Y-m'));
        $lastMonth = Carbon::createFromFormat('!Y-m', self::LAST_FORECAST_MONTH)->startOfMonth();
        $options = [];

        for (; $month->lte($lastMonth); $month->addMonth()) {
            $options[$month->format('Y-m')] = $month->copy()->locale('id')->translatedFormat('F Y');
        }

        return $options;
    }

    private function authorizeFinance(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isAdmin() || $user?->isAssignedToDivision('finance'), 403);
    }

    private function recordBalanceChange(
        FinanceCashAccount $cashAccount,
        string|int|float|null $previousBalance,
        string|int|float|null $newBalance,
        int $changedBy,
    ): void {
        $normalizedPreviousBalance = $this->normalizeBalance($previousBalance);
        $normalizedNewBalance = $this->normalizeBalance($newBalance);

        if ($normalizedPreviousBalance === $normalizedNewBalance) {
            return;
        }

        FinanceCashAccountBalanceHistory::query()->create([
            'cash_account_id' => $cashAccount->id,
            'account_name' => $cashAccount->name,
            'previous_balance' => $normalizedPreviousBalance,
            'new_balance' => $normalizedNewBalance,
            'changed_by' => $changedBy,
        ]);
    }

    private function normalizeBalance(string|int|float|null $balance): ?string
    {
        return $balance === null ? null : number_format((float) $balance, 2, '.', '');
    }
}
