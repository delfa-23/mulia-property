<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'purpose' => ['nullable', Rule::in(['pembangunan', 'marketing'])],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'facility_id' => ['nullable', 'integer', 'exists:common_facilities,id'],
        ]);

        $budgets = Budget::query()
            ->with(['property', 'lot.block', 'facility', 'createdBy'])
            ->when(isset($filters['purpose']), fn ($query) => $query->where('purpose', $filters['purpose']))
            ->when(isset($filters['property_id']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('property_id', $filters['property_id'])
                    ->orWhere(fn ($query) => $query
                        ->whereNull('property_id')
                        ->where('purpose', 'marketing'));
            }))
            ->when(isset($filters['block_id']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->whereHas('lot', fn ($query) => $query->where('block_id', $filters['block_id']))
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }))
            ->when(isset($filters['lot_id']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('lot_id', $filters['lot_id'])
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }))
            ->when(isset($filters['facility_id']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('facility_id', $filters['facility_id'])
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $properties = Property::query()->orderBy('name')->get();
        $blocks = Block::query()->with('property')->orderBy('name')->get();
        $lots = Lot::query()->with('block.property')->orderBy('lot_number')->get();
        $facilities = CommonFacility::query()->with('property')->orderBy('name')->get();

        return view('admin.budgets.index', compact('budgets', 'properties', 'blocks', 'lots', 'facilities', 'filters'));
    }

    public function create(): View
    {
        return view('admin.budgets.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['created_by'] = $request->user()->id;
        Budget::create($validated);

        return redirect()->route('admin.budgets.index')->with('success', 'RAB berhasil ditambahkan.');
    }

    public function edit(Budget $budget): View
    {
        return view('admin.budgets.edit', array_merge(
            ['budget' => $budget],
            $this->formData()
        ));
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $budget->update($this->validated($request));

        return redirect()->route('admin.budgets.index')->with('success', 'RAB berhasil diperbarui.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $budget->delete();

        return redirect()->route('admin.budgets.index')->with('success', 'RAB berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'properties' => Property::query()->orderBy('name')->get(),
            'lots' => Lot::query()->with('block.property')->orderBy('lot_number')->get(),
            'facilities' => CommonFacility::query()->with('property')->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'purpose' => ['required', Rule::in(['pembangunan', 'marketing'])],
            'property_id' => [
                Rule::requiredIf(
                    $request->input('purpose') === 'pembangunan'
                    || $request->filled('lot_id')
                    || $request->filled('facility_id')
                ),
                'nullable',
                'exists:properties,id',
            ],
            'lot_id' => ['nullable', 'exists:lots,id', Rule::prohibitedIf($request->filled('facility_id'))],
            'facility_id' => ['nullable', 'exists:common_facilities,id', Rule::prohibitedIf($request->filled('lot_id'))],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'period_start' => ['nullable', 'date'],
            'period_end' => [
                'nullable',
                'date',
                Rule::when($request->filled('period_start'), ['after_or_equal:period_start']),
            ],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validated['purpose'] === 'marketing' && preg_match('/^\s*material\b/i', $validated['category']) === 1) {
            throw ValidationException::withMessages([
                'category' => 'Kategori material harus dimasukkan ke RAB Pembangunan.',
            ]);
        }

        if (isset($validated['property_id'])) {
            $propertyId = (int) $validated['property_id'];

            if (isset($validated['lot_id']) && ! Lot::query()
                ->whereKey($validated['lot_id'])
                ->whereHas('block', fn ($query) => $query->where('property_id', $propertyId))
                ->exists()) {
                abort(422, 'Kavling tidak termasuk dalam project yang dipilih.');
            }

            if (isset($validated['facility_id']) && ! CommonFacility::query()
                ->whereKey($validated['facility_id'])
                ->where('property_id', $propertyId)
                ->exists()) {
                abort(422, 'Fasilitas tidak termasuk dalam project yang dipilih.');
            }
        }

        return $validated;
    }
}
