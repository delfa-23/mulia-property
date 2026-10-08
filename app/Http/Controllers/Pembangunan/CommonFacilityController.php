<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\CommonFacility;
use App\Models\CommonFacilityHistory;
use App\Models\Property;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return view('pembangunan.common-facilities.index', compact(
            'properties',
            'propertyId',
            'selectedProperty',
            'facilities',
        ));
    }

    public function show(CommonFacility $commonFacility): View
    {
        $commonFacility->load([
            'property',
            'histories.updatedBy',
        ]);

        return view('pembangunan.common-facilities.show', compact('commonFacility'));
    }

    public function update(Request $request, CommonFacility $commonFacility, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'realization' => [
                'required',
                'numeric',
                'min:0',
                'lte:'.$commonFacility->budget,
            ],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($commonFacility, $request, $validated, $activityLogger): void {
            $previousRealization = $commonFacility->realization;
            $realization = $validated['realization'];

            $status = match (true) {
                $commonFacility->status === 'cancelled' => 'cancelled',
                (float) $realization <= 0 => 'planned',
                (float) $commonFacility->budget > 0 && (float) $realization >= (float) $commonFacility->budget => 'completed',
                default => 'in_progress',
            };

            $commonFacility->update([
                'realization' => $realization,
                'status' => $status,
            ]);

            CommonFacilityHistory::create([
                'common_facility_id' => $commonFacility->id,
                'previous_realization' => $previousRealization,
                'realization' => $realization,
                'updated_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);
            $activityLogger->record($request, 'facility.realization_updated', $commonFacility, 'Realisasi fasilitas umum diperbarui.');
        });

        return redirect()
            ->route('pembangunan.common-facilities.show', $commonFacility)
            ->with('success', 'Realisasi fasilitas umum berhasil diperbarui.');
    }
}
