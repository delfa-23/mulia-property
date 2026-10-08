<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\ExpenseTransaction;
use App\Models\ExpenseTransactionHistory;
use App\Models\IncomeTransaction;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
        ]);
        $propertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;
        $selectedLot = isset($validated['lot_id'])
            ? Lot::query()
                ->with('block')
                ->when($propertyId, fn ($query) => $query->whereHas(
                    'block',
                    fn ($query) => $query->where('property_id', $propertyId)
                ))
                ->find($validated['lot_id'])
            : null;

        if ($selectedLot !== null) {
            $propertyId = $selectedLot->block->property_id;
        }

        $lotId = $selectedLot?->id;

        $properties = Property::query()->orderBy('name')->get();
        $lots = $propertyId
            ? Lot::query()
                ->with('block')
                ->whereHas('block', fn ($query) => $query->where('property_id', $propertyId))
                ->orderBy('block_id')
                ->orderBy('lot_number')
                ->get()
            : collect();
        $filterRecords = fn ($query) => $query
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->when($lotId, fn ($query) => $query->where(function ($query) use ($lotId): void {
                $query->where('lot_id', $lotId)
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }));
        $budgets = Budget::query()
            ->with(['property', 'lot.block', 'facility'])
            ->tap($filterRecords)
            ->latest()
            ->paginate(10, ['*'], 'budgets_page')
            ->withQueryString();
        $expenses = ExpenseTransaction::query()
            ->with(['property', 'lot.block', 'facility', 'createdBy'])
            ->tap($filterRecords)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(10, ['*'], 'expenses_page')
            ->withQueryString();

        $totalBudget = $filterRecords(Budget::query())->sum('amount');
        $totalExpense = $filterRecords(ExpenseTransaction::query())->sum('amount');
        $totalIncome = IncomeTransaction::query()
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->sum('amount');

        return view('pembangunan.finance.index', compact(
            'properties',
            'lots',
            'budgets',
            'expenses',
            'totalBudget',
            'totalExpense',
            'totalIncome',
            'propertyId',
            'lotId',
        ));
    }

    public function create(): View
    {
        return view('pembangunan.finance.create', [
            'properties' => Property::query()->orderBy('name')->get(),
            'lots' => Lot::query()->with('block.property')->orderBy('lot_number')->get(),
            'facilities' => CommonFacility::query()->with('property')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedExpense($request);
        $validated['transaction_number'] = 'EXP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        $validated['created_by'] = $request->user()->id;

        ExpenseTransaction::create($validated);

        return redirect()
            ->route('finance.index')
            ->with('success', 'Pengeluaran berhasil ditambahkan.');
    }

    public function edit(ExpenseTransaction $expenseTransaction): View
    {
        return view('pembangunan.finance.edit', [
            'expenseTransaction' => $expenseTransaction,
            'properties' => Property::query()->orderBy('name')->get(),
            'lots' => Lot::query()->with('block.property')->orderBy('lot_number')->get(),
            'facilities' => CommonFacility::query()->with('property')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ExpenseTransaction $expenseTransaction): RedirectResponse
    {
        $validated = $this->validatedExpense($request);

        DB::transaction(function () use ($request, $expenseTransaction, $validated): void {
            ExpenseTransactionHistory::create([
                'expense_transaction_id' => $expenseTransaction->id,
                'previous_amount' => $expenseTransaction->amount,
                'amount' => $validated['amount'],
                'previous_category' => $expenseTransaction->category,
                'category' => $validated['category'],
                'previous_transaction_date' => $expenseTransaction->transaction_date,
                'transaction_date' => $validated['transaction_date'],
                'previous_description' => $expenseTransaction->description,
                'description' => $validated['description'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $expenseTransaction->update($validated);
        });

        return redirect()
            ->route('finance.index')
            ->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(ExpenseTransaction $expenseTransaction): RedirectResponse
    {
        $expenseTransaction->delete();

        return redirect()
            ->route('finance.index')
            ->with('success', 'Pengeluaran berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedExpense(Request $request): array
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'lot_id' => ['nullable', 'exists:lots,id', Rule::prohibitedIf($request->filled('facility_id'))],
            'facility_id' => ['nullable', 'exists:common_facilities,id', Rule::prohibitedIf($request->filled('lot_id'))],
            'transaction_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
        ], [
            'lot_id.prohibited' => 'Pilih kavling atau fasilitas saja, bukan keduanya.',
            'facility_id.prohibited' => 'Pilih kavling atau fasilitas saja, bukan keduanya.',
        ]);

        $propertyId = (int) $validated['property_id'];

        if (isset($validated['lot_id'])) {
            $lotBelongsToProperty = Lot::query()
                ->whereKey($validated['lot_id'])
                ->whereHas('block', fn ($query) => $query->where('property_id', $propertyId))
                ->exists();

            if (! $lotBelongsToProperty) {
                abort(422, 'Kavling tidak termasuk dalam project yang dipilih.');
            }
        }

        if (isset($validated['facility_id'])) {
            $facilityBelongsToProperty = CommonFacility::query()
                ->whereKey($validated['facility_id'])
                ->where('property_id', $propertyId)
                ->exists();

            if (! $facilityBelongsToProperty) {
                abort(422, 'Fasilitas tidak termasuk dalam project yang dipilih.');
            }
        }

        return $validated;
    }
}
