<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommonFacility;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommonFacilityController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);
        $propertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;
        $properties = Property::query()
            ->withCount('commonFacilities')
            ->orderBy('name')
            ->get();
        $selectedProperty = $propertyId === null
            ? null
            : $properties->firstWhere('id', $propertyId);
        $facilities = $selectedProperty === null
            ? null
            : CommonFacility::query()
                ->where('property_id', $selectedProperty->id)
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString();

        return view('admin.common-facilities.index', compact(
            'facilities',
            'properties',
            'propertyId',
            'selectedProperty',
        ));
    }

    public function create(): View
    {
        $properties = Property::query()
            ->orderBy('name')
            ->get();

        return view(
            'admin.common-facilities.create',
            compact('properties')
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('common_facilities', 'name')
                    ->where('property_id', $request->integer('property_id')),
            ],
            'description' => ['nullable', 'string'],
            'budget' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['realization'] = 0;
        $validated['status'] = 'planned';

        CommonFacility::create($validated);

        return redirect()
            ->route('admin.common-facilities.index')
            ->with('success', 'Fasilitas umum berhasil dibuat.');
    }

    public function edit(CommonFacility $commonFacility): View
    {
        $properties = Property::query()
            ->orderBy('name')
            ->get();

        return view(
            'admin.common-facilities.edit',
            compact('commonFacility', 'properties')
        );
    }

    public function update(
        Request $request,
        CommonFacility $commonFacility
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('common_facilities', 'name')
                    ->where('property_id', $request->integer('property_id'))
                    ->ignore($commonFacility),
            ],
            'description' => ['nullable', 'string'],
            'budget' => ['required', 'numeric', 'min:0'],
        ]);

        $commonFacility->update($validated);

        return redirect()
            ->route('admin.common-facilities.index')
            ->with('success', 'Fasilitas umum berhasil diperbarui.');
    }

    public function destroy(
        CommonFacility $commonFacility
    ): RedirectResponse {
        $commonFacility->delete();

        return redirect()
            ->route('admin.common-facilities.index')
            ->with('success', 'Fasilitas umum berhasil dihapus.');
    }
}
