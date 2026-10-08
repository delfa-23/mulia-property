<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\CommonFacility;
use App\Models\ConstructionProgress;
use App\Models\ConstructionProgressHistory;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
        ]);
        $propertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;
        $selectedBlock = isset($validated['block_id'])
            ? Block::query()
                ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
                ->find($validated['block_id'])
            : null;

        if ($selectedBlock !== null) {
            $propertyId ??= $selectedBlock->property_id;
        }

        $lotQuery = Lot::query()
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($selectedBlock, fn ($query) => $query->where('block_id', $selectedBlock->id));
        $selectedLot = isset($validated['lot_id']) ? $lotQuery->find($validated['lot_id']) : null;

        if ($selectedLot !== null) {
            $selectedBlock = $selectedLot->block;
            $propertyId = $selectedBlock->property_id;
        }

        $blockId = $selectedBlock?->id;
        $lotId = $selectedLot?->id;
        $filterLots = fn ($query) => $query
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($blockId, fn ($query) => $query->where('block_id', $blockId))
            ->when($lotId, fn ($query) => $query->whereKey($lotId));

        $properties = Property::query()->orderBy('name')->get();
        $blocks = Block::query()
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->orderBy('name')
            ->get();
        $lots = Lot::query()
            ->with('block')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($blockId, fn ($query) => $query->where('block_id', $blockId))
            ->orderBy('lot_number')
            ->get();
        $lotStats = $filterLots(Lot::query());
        $physicalProgressByLot = ConstructionProgress::query()
            ->whereNotNull('stage_id')
            ->when($propertyId || $blockId || $lotId, fn ($query) => $query->whereHas('lot', $filterLots))
            ->select('lot_id')
            ->selectRaw('MAX(progress) as physical_progress')
            ->groupBy('lot_id')
            ->get();

        $stats = [
            'total_properties' => Property::query()->when($propertyId, fn ($query) => $query->whereKey($propertyId))->count(),
            'total_blocks' => Block::query()
                ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
                ->when($blockId, fn ($query) => $query->whereKey($blockId))
                ->count(),
            'total_lots' => (clone $lotStats)->count(),

            'available_lots' => (clone $lotStats)->where('status', 'available')->count(),
            'booked_lots' => (clone $lotStats)->where('status', 'booked')->count(),
            'process_lots' => (clone $lotStats)->where('status', 'process')->count(),
            'akad_lots' => (clone $lotStats)->where('status', 'akad')->count(),
            'finish_lots' => (clone $lotStats)->where('status', 'finish')->count(),

            'physical_progress' => round((float) $physicalProgressByLot->avg('physical_progress'), 2),
            'facility_progress' => round((float) CommonFacility::query()
                ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
                ->get()
                ->avg(fn (CommonFacility $facility): float => $facility->progress), 2),
        ];

        $lotStatusDefinitions = [
            'available' => ['label' => 'Tersedia', 'color' => '#009851'],
            'booked' => ['label' => 'Booking', 'color' => '#3b82f6'],
            'process' => ['label' => 'Proses Berkas', 'color' => '#ff9100'],
            'akad' => ['label' => 'Akad', 'color' => '#1e3a5f'],
            'finish' => ['label' => 'Finish', 'color' => '#10b981'],
            'blocked' => ['label' => 'Blocked', 'color' => '#64748b'],
            'cancelled' => ['label' => 'Dibatalkan', 'color' => '#ef4444'],
        ];
        $lotStatusTotal = $stats['total_lots'];
        $lotStatusChart = collect($lotStatusDefinitions)
            ->map(function (array $statusData, string $status) use ($lotStats, $lotStatusTotal): array {
                $value = (clone $lotStats)->where('status', $status)->count();

                return [
                    'status' => $status,
                    'label' => $statusData['label'],
                    'color' => $statusData['color'],
                    'value' => $value,
                    'percent' => $lotStatusTotal > 0 ? round($value / $lotStatusTotal * 100, 1) : 0,
                ];
            })
            ->values();

        $stageProgress = ConstructionProgress::query()
            ->when($propertyId || $blockId || $lotId, fn ($query) => $query->whereHas('lot', $filterLots))
            ->select('stage_id')
            ->selectRaw('AVG(progress) as average_progress')
            ->groupBy('stage_id')
            ->with('stage:id,name,order')
            ->get()
            ->filter(fn (ConstructionProgress $progress): bool => $progress->stage !== null)
            ->sortBy(fn (ConstructionProgress $progress): int => (int) $progress->stage->order)
            ->map(fn (ConstructionProgress $progress): array => [
                'name' => $progress->stage->name,
                'progress' => round((float) $progress->average_progress, 2),
            ])
            ->values();

        $progressHistory = ConstructionProgressHistory::query()
            ->whereBetween('created_at', [now()->startOfMonth()->subMonths(5), now()->endOfMonth()])
            ->when($propertyId || $blockId || $lotId, fn ($query) => $query->whereHas('constructionProgress.lot', $filterLots))
            ->get(['progress', 'created_at']);
        $monthlyPhysicalProgressChart = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($progressHistory): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);
                $monthlyUpdates = $progressHistory->filter(fn (ConstructionProgressHistory $history): bool => $history->created_at?->format('Y-m') === $month->format('Y-m'));
                $averageProgress = $monthlyUpdates->isEmpty() ? null : round((float) $monthlyUpdates->avg('progress'), 2);

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M Y'),
                    'progress' => $averageProgress,
                    'updates' => $monthlyUpdates->count(),
                ];
            })
            ->values();

        $facilityProgressChart = CommonFacility::query()
            ->with('property')
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->get()
            ->map(fn (CommonFacility $facility): array => [
                'name' => $facility->name,
                'property' => $facility->property?->name,
                'progress' => round((float) $facility->progress, 2),
            ])
            ->sortByDesc('progress')
            ->take(8)
            ->values();

        $latestProgressUpdates = ConstructionProgress::query()
            ->with(['lot.block.property', 'stage', 'updatedBy'])
            ->whereNotNull('stage_id')
            ->when($propertyId || $blockId || $lotId, fn ($query) => $query->whereHas('lot', $filterLots))
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $latestFacilityUpdates = CommonFacility::query()
            ->with('property')
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view(
            'pembangunan.dashboard',
            compact(
                'stats',
                'latestProgressUpdates',
                'latestFacilityUpdates',
                'properties',
                'blocks',
                'lots',
                'propertyId',
                'blockId',
                'lotId',
                'lotStatusChart',
                'lotStatusTotal',
                'stageProgress',
                'monthlyPhysicalProgressChart',
                'facilityProgressChart',
            )
        );
    }
}
