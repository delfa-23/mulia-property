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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacilityProgressController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $properties = Property::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $facilities = CommonFacility::query()
            ->with('property')
            ->withSum([
                'budgets as pembangunan_budget_total' => fn ($query) => $query->where('purpose', 'pembangunan'),
            ], 'amount')
            ->when(
                isset($validated['property_id']),
                fn ($query) => $query->where('property_id', $validated['property_id'])
            )
            ->orderBy('property_id')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('pembangunan.progress.facilities.index', [
            'properties' => $properties,
            'facilities' => $facilities,
            'propertyId' => isset($validated['property_id']) ? (int) $validated['property_id'] : null,
        ]);
    }

    public function show(CommonFacility $commonFacility): View
    {
        $commonFacility->load([
            'property',
            'histories.updatedBy',
            'budgets' => fn ($query) => $query->where('purpose', 'pembangunan')->orderBy('category'),
        ]);
        $rabTotal = (float) $commonFacility->budgets->sum('amount');
        $budget = $rabTotal > 0 ? $rabTotal : (float) $commonFacility->budget;

        return view('pembangunan.progress.facilities.show', [
            'facility' => $commonFacility,
            'budget' => $budget,
            'progress' => $budget > 0
                ? min(100, ((float) $commonFacility->realization / $budget) * 100)
                : 0,
        ]);
    }

    public function update(
        Request $request,
        CommonFacility $commonFacility,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_if((float) $commonFacility->realization <= 0, 404);

        return $this->saveProgress($request, $commonFacility, $activityLogger);
    }

    public function store(
        Request $request,
        CommonFacility $commonFacility,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_if((float) $commonFacility->realization > 0, 409, 'Progress fasilitas sudah dibuat. Gunakan aksi edit.');

        return $this->saveProgress($request, $commonFacility, $activityLogger);
    }

    public function destroy(
        Request $request,
        CommonFacility $commonFacility,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        if ((float) $commonFacility->realization <= 0) {
            throw ValidationException::withMessages([
                'realization' => 'Belum ada progress fasilitas yang dapat dihapus.',
            ]);
        }

        DB::transaction(function () use ($commonFacility, $request, $activityLogger): void {
            $previousRealization = $commonFacility->realization;
            $commonFacility->update([
                'realization' => 0,
                'status' => $commonFacility->status === 'cancelled' ? 'cancelled' : 'planned',
            ]);
            CommonFacilityHistory::create([
                'common_facility_id' => $commonFacility->id,
                'previous_realization' => $previousRealization,
                'realization' => 0,
                'updated_by' => $request->user()->id,
                'notes' => 'Progress saat ini dihapus; catatan riwayat sebelumnya tetap dipertahankan.',
            ]);

            $activityLogger->record(
                $request,
                'facility.progress_deleted',
                $commonFacility,
                'Progress fasilitas umum dihapus; riwayat perubahan tetap disimpan.'
            );
        });

        return redirect()
            ->route('pembangunan.progress.facilities.show', $commonFacility)
            ->with('success', 'Progress fasilitas umum dihapus. Riwayat perubahan tetap tersimpan.');
    }

    private function saveProgress(
        Request $request,
        CommonFacility $commonFacility,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $rabTotal = (float) $commonFacility->budgets()
            ->where('purpose', 'pembangunan')
            ->sum('amount');
        $budget = $rabTotal > 0 ? $rabTotal : (float) $commonFacility->budget;
        $validated = $request->validate([
            'realization' => ['required', 'numeric', 'min:0', 'lte:'.$budget],
            'notes' => ['nullable', 'string'],
        ], [
            'realization.lte' => 'Realisasi tidak boleh melebihi anggaran RAB fasilitas.',
        ]);

        if ($commonFacility->status === 'cancelled' && (float) $validated['realization'] !== (float) $commonFacility->realization) {
            throw ValidationException::withMessages([
                'realization' => 'Progress fasilitas yang dibatalkan tidak dapat diubah.',
            ]);
        }

        DB::transaction(function () use ($commonFacility, $request, $validated, $budget, $activityLogger): void {
            $previousRealization = $commonFacility->realization;
            $realization = (float) $validated['realization'];

            $status = match (true) {
                $commonFacility->status === 'cancelled' => 'cancelled',
                $realization <= 0 => 'planned',
                $budget > 0 && $realization >= $budget => 'completed',
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

            $activityLogger->record(
                $request,
                'facility.realization_updated',
                $commonFacility,
                'Progress dan realisasi fasilitas umum diperbarui.'
            );
        });

        return redirect()
            ->route('pembangunan.progress.facilities.show', $commonFacility)
            ->with('success', 'Progress fasilitas umum berhasil disimpan.');
    }
}
