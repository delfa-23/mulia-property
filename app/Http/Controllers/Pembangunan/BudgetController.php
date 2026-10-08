<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function create(): View
    {
        return view('pembangunan.finance.budget-create', [
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'lots' => Lot::query()->with('block.property')->orderBy('lot_number')->get(),
            'facilities' => CommonFacility::query()->with('property')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
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

        $validated['created_by'] = $request->user()->id;
        Budget::create($validated);

        return redirect()
            ->route('finance.index')
            ->with('success', 'RAB berhasil ditambahkan.');
    }
}
