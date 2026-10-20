<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceCashAccount;
use App\Models\FinanceCashAccountBalanceHistory;
use App\Models\FinanceCashFlow;
use App\Models\Property;
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
    public function index(Request $request): View
    {
        $this->authorizeFinance($request);

        $today = today();
        $currentMonth = $today->format('Y-m');
        $historyMonthOptions = FinanceCashFlow::query()
            ->select('due_date')
            ->distinct()
            ->orderBy('due_date')
            ->pluck('due_date')
            ->map(fn ($dueDate): string => Carbon::parse($dueDate)->format('Y-m'))
            ->push($currentMonth)
            ->unique()
            ->sortDesc()
            ->values();
        $historyMonthLabels = $historyMonthOptions->mapWithKeys(fn (string $month): array => [
            $month => Carbon::createFromFormat('!Y-m', $month)->locale('id')->translatedFormat('F Y'),
        ]);
        $validated = $request->validate([
            'history_month' => ['nullable', 'date_format:Y-m', Rule::in($historyMonthOptions->all())],
            'site_plan_property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);
        $selectedHistoryMonth = $validated['history_month'] ?? $currentMonth;
        $historyStart = Carbon::createFromFormat('!Y-m', $selectedHistoryMonth)->startOfMonth();
        $historyEnd = $historyStart->copy()->endOfMonth();
        $historyIsArchived = $historyStart->lt($today->copy()->startOfMonth());
        $historyIsFuture = $historyStart->gt($today->copy()->startOfMonth());
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
            ->whereBetween('due_date', [$historyStart->toDateString(), $historyEnd->toDateString()]);
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
            ->mapWithKeys(function (int $weekNumber) use ($today): array {
                $start = $today->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($weekNumber - 1);
                $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

                return [$weekNumber => [
                    'label' => $start->format('d/m').' – '.$end->format('d/m/Y'),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'min' => $weekNumber === 1 && $today->gt($start) ? $today->toDateString() : $start->toDateString(),
                ]];
            });
        $weekDateBounds = $weekOptions->map(fn (array $week): array => [
            'min' => $week['min'],
            'max' => $week['end'],
        ]);
        $endDate = $weekOptions->last()['end'];
        $cashFlowPlans = FinanceCashFlow::query()
            ->with(['property', 'createdBy'])
            ->whereBetween('due_date', [$today->toDateString(), $endDate])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
        $cashFlowForecasts = $weekOptions->mapWithKeys(function (array $week, int $weekNumber) use ($cashFlowPlans): array {
            $plansInWeek = $cashFlowPlans->filter(
                fn (FinanceCashFlow $plan): bool => $plan->due_date->between($week['min'], $week['end']),
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
            'historyIsFuture' => $historyIsFuture,
            'historyMonthLabels' => $historyMonthLabels,
            'historySummary' => $historySummary,
            'properties' => $properties,
            'selectedProperty' => $selectedProperty,
            'sitePlanPropertyId' => $sitePlanPropertyId,
            'sitePlanLots' => $sitePlanLots,
            'sitePlanAreas' => $sitePlanAreas,
            'selectedHistoryMonth' => $selectedHistoryMonth,
            'weekOptions' => $weekOptions,
            'weekDateBounds' => $weekDateBounds,
            'selectedWeek' => (string) old('week_number', 1),
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

        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:150'],
            'direction' => ['required', Rule::in(['income', 'outcome'])],
            'status' => ['required', Rule::in(['confirmed', 'potential', 'forecast'])],
            'week_number' => ['required', 'integer', 'between:1,4'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
        ]);

        $weekStart = today()->startOfWeek(Carbon::MONDAY)->addWeeks((int) $validated['week_number'] - 1);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $minimumDate = (int) $validated['week_number'] === 1 && today()->gt($weekStart)
            ? today()
            : $weekStart;
        $dueDate = Carbon::parse($validated['due_date'])->startOfDay();

        if ($dueDate->lt($minimumDate) || $dueDate->gt($weekEnd)) {
            throw ValidationException::withMessages([
                'due_date' => 'Tanggal rencana harus berada pada minggu yang dipilih dan tidak boleh sudah lewat.',
            ]);
        }

        unset($validated['week_number']);
        FinanceCashFlow::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return to_route('keuangan.cash-position.index')->with('success', 'Rencana pemasukan/pengeluaran berhasil ditambahkan.');
    }

    public function destroyCashFlow(Request $request, FinanceCashFlow $cashFlow): RedirectResponse
    {
        $this->authorizeFinance($request);
        $historyMonth = $cashFlow->due_date->format('Y-m');
        $cashFlow->delete();

        return to_route('keuangan.cash-position.index', ['history_month' => $historyMonth])
            ->with('success', 'Rencana arus kas berhasil dihapus.');
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
