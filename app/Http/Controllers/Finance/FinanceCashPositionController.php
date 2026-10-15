<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\MarketingCashAccount;
use App\Models\MarketingCashFlow;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $cashFlowPlans = MarketingCashFlow::query()
            ->with(['property', 'createdBy'])
            ->whereBetween('due_date', [$today->toDateString(), $endDate])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
        $cashFlowForecasts = $weekOptions->mapWithKeys(function (array $week, int $weekNumber) use ($cashFlowPlans): array {
            $plansInWeek = $cashFlowPlans->filter(
                fn (MarketingCashFlow $plan): bool => $plan->due_date->between($week['min'], $week['end']),
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
            'cashAccounts' => MarketingCashAccount::query()->orderBy('id')->get(),
            'cashFlowForecasts' => $cashFlowForecasts,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'weekOptions' => $weekOptions,
            'weekDateBounds' => $weekDateBounds,
            'selectedWeek' => (string) old('week_number', 1),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:marketing_cash_accounts,name'],
            'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        MarketingCashAccount::query()->create([
            'account_key' => 'finance-'.Str::uuid(),
            'name' => trim($validated['name']),
            'balance' => $validated['balance'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        return to_route('keuangan.cash-position.index')->with('success', 'Rekening berhasil ditambahkan.');
    }

    public function updateAccount(Request $request, MarketingCashAccount $cashAccount): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'balance' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $cashAccount->update([
            'balance' => $validated['balance'],
            'updated_by' => $request->user()->id,
        ]);

        return to_route('keuangan.cash-position.index')->with('success', 'Saldo rekening berhasil diperbarui.');
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
        MarketingCashFlow::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return to_route('keuangan.cash-position.index')->with('success', 'Rencana pemasukan/pengeluaran berhasil ditambahkan.');
    }

    public function destroyCashFlow(Request $request, MarketingCashFlow $cashFlow): RedirectResponse
    {
        $this->authorizeFinance($request);
        $cashFlow->delete();

        return to_route('keuangan.cash-position.index')->with('success', 'Rencana arus kas berhasil dihapus.');
    }

    private function authorizeFinance(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isAdmin() || $user?->isAssignedToDivision('keuangan'), 403);
    }
}
