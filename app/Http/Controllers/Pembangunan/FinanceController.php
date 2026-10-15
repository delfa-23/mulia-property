<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\ExpenseTransaction;
use App\Models\ExpenseTransactionHistory;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'division' => ['nullable', Rule::in(['marketing', 'pembangunan'])],
        ]);
        $division = $request->user()->isAdmin()
            ? ($validated['division'] ?? 'marketing')
            : $request->user()->division?->slug;
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
        $lots = Lot::query()
            ->with('block')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->orderBy('block_id')
            ->orderBy('lot_number')
            ->get();
        $filterRecords = fn ($query) => $query
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->when($lotId, fn ($query) => $query->where(function ($query) use ($lotId): void {
                $query->where('lot_id', $lotId)
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }))
            ->when($division === 'marketing', fn ($query) => $query
                ->whereRaw('LOWER(category) NOT LIKE ?', ['material%']));
        $expenses = ExpenseTransaction::query()
            ->with(['property', 'lot.block', 'facility', 'createdBy'])
            ->when($division, fn ($query) => $query->where('division', $division))
            ->tap($filterRecords)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15, ['*'], 'expenses_page')
            ->withQueryString();
        $totalExpense = ExpenseTransaction::query()
            ->when($division, fn ($query) => $query->where('division', $division))
            ->tap($filterRecords)
            ->sum('amount');
        $budgetsQuery = Budget::query()
            ->with(['property', 'lot.block', 'facility', 'createdBy'])
            ->where('purpose', 'pembangunan')
            ->tap($filterRecords)
            ->latest();
        $budgets = (clone $budgetsQuery)
            ->paginate(15, ['*'], 'budgets_page')
            ->withQueryString();
        $totalBudget = (clone $budgetsQuery)->sum('amount');
        $totalConstructionDeviation = (float) $totalBudget - (float) $totalExpense;
        $constructionBudgetCategoryTotals = (clone $budgetsQuery)
            ->reorder()
            ->selectRaw('property_id, category, SUM(amount) as total')
            ->groupBy('property_id', 'category')
            ->get();
        $constructionExpenseCategoryTotals = ExpenseTransaction::query()
            ->where('division', 'pembangunan')
            ->tap($filterRecords)
            ->selectRaw('property_id, category, SUM(amount) as total')
            ->groupBy('property_id', 'category')
            ->get();
        $constructionCategorySummaries = collect();

        foreach ($constructionBudgetCategoryTotals as $budgetCategoryTotal) {
            $summaryKey = ($budgetCategoryTotal->property_id ?? 'none').'|'.$budgetCategoryTotal->category;
            $constructionCategorySummaries->put($summaryKey, [
                'property_id' => $budgetCategoryTotal->property_id,
                'category' => $budgetCategoryTotal->category,
                'budget' => (float) $budgetCategoryTotal->total,
                'expense' => 0.0,
            ]);
        }

        foreach ($constructionExpenseCategoryTotals as $expenseCategoryTotal) {
            $summaryKey = ($expenseCategoryTotal->property_id ?? 'none').'|'.$expenseCategoryTotal->category;
            $summary = $constructionCategorySummaries->get($summaryKey, [
                'property_id' => $expenseCategoryTotal->property_id,
                'category' => $expenseCategoryTotal->category,
                'budget' => 0.0,
                'expense' => 0.0,
            ]);
            $summary['expense'] = (float) $expenseCategoryTotal->total;
            $constructionCategorySummaries->put($summaryKey, $summary);
        }

        $propertiesById = $properties->keyBy('id');
        $constructionCategorySummaries = $constructionCategorySummaries
            ->map(function (array $summary) use ($propertiesById): array {
                $summary['property'] = $propertiesById->get($summary['property_id']);
                $summary['deviation'] = $summary['budget'] - $summary['expense'];

                return $summary;
            })
            ->sortBy(fn (array $summary): string => strtolower(
                ($summary['property']?->name ?? '').'|'.$summary['category']
            ))
            ->values();
        $marketingBudgetsQuery = Budget::query()
            ->with(['property', 'lot.block', 'facility'])
            ->withSum([
                'expenses as marketing_expenses_sum' => fn ($query) => $query->where('division', 'marketing'),
            ], 'amount')
            ->where('purpose', 'marketing')
            ->whereRaw('LOWER(category) NOT LIKE ?', ['material%'])
            ->when($propertyId, fn ($query) => $query->where(fn ($query) => $query
                ->where('property_id', $propertyId)
                ->orWhereNull('property_id')))
            ->when($lotId, fn ($query) => $query->where(fn ($query) => $query
                ->where('lot_id', $lotId)
                ->orWhere(fn ($query) => $query
                    ->whereNull('lot_id')
                    ->whereNull('facility_id'))))
            ->latest();
        $marketingBudgets = (clone $marketingBudgetsQuery)
            ->paginate(15, ['*'], 'marketing_budgets_page')
            ->withQueryString();
        $totalMarketingBudget = (clone $marketingBudgetsQuery)->sum('amount');
        $totalMarketingDeviation = (float) $totalMarketingBudget - (float) $totalExpense;
        $budgetLotIds = $budgets->getCollection()->pluck('lot_id')->filter()->unique()->values();
        $lotBudgetDetails = $budgetLotIds->isEmpty()
            ? collect()
            : Budget::query()
                ->where('purpose', 'pembangunan')
                ->whereIn('lot_id', $budgetLotIds)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('lot_id')
                ->map(fn ($lotBudgets): array => [
                    'items' => $lotBudgets,
                    'total' => (float) $lotBudgets->sum('amount'),
                ]);
        $housePrices = null;
        $totalHousePrice = 0;

        if ($division === 'pembangunan') {
            $housePricesQuery = Lot::query()
                ->with(['block.property'])
                ->when($propertyId, fn ($query) => $query->whereHas(
                    'block',
                    fn ($query) => $query->where('property_id', $propertyId)
                ))
                ->when($lotId, fn ($query) => $query->whereKey($lotId))
                ->orderBy('block_id')
                ->orderBy('lot_number');
            $housePrices = $housePricesQuery->paginate(15, ['*'], 'prices_page')->withQueryString();
            $totalHousePrice = (clone $housePricesQuery)->sum('house_price');
        }

        return view('pembangunan.finance.index', compact(
            'properties',
            'lots',
            'expenses',
            'budgets',
            'marketingBudgets',
            'lotBudgetDetails',
            'housePrices',
            'totalExpense',
            'totalBudget',
            'totalConstructionDeviation',
            'constructionCategorySummaries',
            'totalMarketingBudget',
            'totalMarketingDeviation',
            'totalHousePrice',
            'propertyId',
            'lotId',
            'division',
        ));
    }

    public function create(Request $request): View
    {
        $division = $request->user()->isAdmin()
            ? ($request->validate(['division' => ['nullable', Rule::in(['marketing', 'pembangunan'])]])['division'] ?? 'marketing')
            : $request->user()->division->slug;

        return view('pembangunan.finance.create', array_merge(
            $this->formData($division),
            ['division' => $division]
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $division = $this->divisionForWrite($request);
        $validated = $this->validatedExpense($request, $division);
        $validated['division'] = $division;
        $validated['transaction_number'] = 'EXP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        $validated['created_by'] = $request->user()->id;
        ExpenseTransaction::create($validated);

        return redirect()
            ->route('finance.index', ['division' => $validated['division']])
            ->with('success', 'Pengeluaran berhasil ditambahkan.');
    }

    public function edit(Request $request, ExpenseTransaction $expenseTransaction): View
    {
        $this->authorizeExpense($request, $expenseTransaction);

        return view('pembangunan.finance.edit', array_merge(
            $this->formData($expenseTransaction->division),
            [
                'expenseTransaction' => $expenseTransaction,
                'division' => $expenseTransaction->division,
            ]
        ));
    }

    public function update(Request $request, ExpenseTransaction $expenseTransaction): RedirectResponse
    {
        $this->authorizeExpense($request, $expenseTransaction);
        $validated = $this->validatedExpense($request, $expenseTransaction->division);

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
            ->route('finance.index', ['division' => $expenseTransaction->division])
            ->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Request $request, ExpenseTransaction $expenseTransaction): RedirectResponse
    {
        $this->authorizeExpense($request, $expenseTransaction);
        $division = $expenseTransaction->division;
        $expenseTransaction->delete();

        return redirect()
            ->route('finance.index', ['division' => $division])
            ->with('success', 'Pengeluaran berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(string $division): array
    {
        return [
            'expenseBudgets' => Budget::query()
                ->with(['property', 'lot.block.property', 'facility.property'])
                ->where('purpose', $division)
                ->when($division === 'marketing', fn ($query) => $query
                    ->whereRaw('LOWER(category) NOT LIKE ?', ['material%']))
                ->whereNotNull('category')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function divisionForWrite(Request $request): string
    {
        if ($request->user()->isAdmin()) {
            return $request->validate([
                'division' => ['nullable', Rule::in(['marketing', 'pembangunan'])],
            ])['division'] ?? 'marketing';
        }

        return $request->user()->division->slug;
    }

    private function authorizeExpense(Request $request, ExpenseTransaction $expenseTransaction): void
    {
        abort_unless(
            $request->user()->isAdmin() ||
                $request->user()->division?->slug === $expenseTransaction->division,
            403
        );
    }

    /** @return array<string, mixed> */
    private function validatedExpense(Request $request, string $division): array
    {
        $budgetField = $division === 'marketing' ? 'marketing_budget_id' : 'construction_budget_id';
        $validated = $request->validate([
            'marketing_budget_id' => [
                Rule::requiredIf($division === 'marketing'),
                'nullable',
                Rule::prohibitedIf($division !== 'marketing'),
                'integer',
                'exists:budgets,id',
            ],
            'construction_budget_id' => [
                Rule::requiredIf($division === 'pembangunan'),
                'nullable',
                Rule::prohibitedIf($division !== 'pembangunan'),
                'integer',
                'exists:budgets,id',
            ],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'construction_budget_id.required' => 'Pilih RAB Pembangunan yang akan digunakan.',
        ]);

        $budget = Budget::query()
            ->with(['lot.block', 'facility'])
            ->whereKey($validated[$budgetField])
            ->where('purpose', $division)
            ->first();

        if (! $budget) {
            throw ValidationException::withMessages([
                $budgetField => 'Pilih RAB yang sesuai dengan kategori, perumahan, dan sasaran pengeluaran.',
            ]);
        }

        $budgetPropertyId = $budget->property_id
            ?? $budget->lot?->block?->property_id
            ?? $budget->facility?->property_id;
        $propertyId = $budgetPropertyId;
        $lotId = $budget->lot_id;
        $facilityId = $budget->facility_id;

        if ($lotId !== null && $facilityId !== null) {
            throw ValidationException::withMessages([
                $budgetField => 'Pilih RAB yang sesuai dengan kategori, perumahan, dan sasaran pengeluaran.',
            ]);
        }

        if ($division === 'marketing' && preg_match('/^\s*material\b/i', $budget->category) === 1) {
            throw ValidationException::withMessages([
                $budgetField => 'Kategori material dicatat pada Keuangan Pembangunan, bukan Marketing.',
            ]);
        }

        if ($lotId !== null && ! Lot::query()
            ->whereKey($lotId)
            ->when($propertyId !== null, fn ($query) => $query->whereHas(
                'block',
                fn ($query) => $query->where('property_id', $propertyId)
            ))
            ->exists()) {
            abort(422, 'Kavling tidak termasuk dalam project yang dipilih.');
        }

        if ($facilityId !== null && ! CommonFacility::query()
            ->whereKey($facilityId)
            ->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))
            ->exists()) {
            abort(422, 'Fasilitas tidak termasuk dalam project yang dipilih.');
        }

        $validated['property_id'] = $propertyId;
        $validated['lot_id'] = $lotId;
        $validated['facility_id'] = $facilityId;
        $validated['category'] = $budget->category;
        $validated['budget_id'] = $budget->id;
        unset($validated[$budgetField]);

        return $validated;
    }
}
