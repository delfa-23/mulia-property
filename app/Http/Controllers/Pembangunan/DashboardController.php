<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\ConstructionProgress;
use App\Models\ConstructionStage;
use App\Models\ExpenseTransaction;
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
        $selectedProperty = $properties->firstWhere('id', $propertyId);
        $lots = Lot::query()
            ->with('block')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($blockId, fn ($query) => $query->where('block_id', $blockId))
            ->orderBy('lot_number')
            ->get();
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
        $lotStats = $filterLots(Lot::query());
        $dashboardLots = (clone $lotStats)
            ->select(['id', 'block_id', 'status'])
            ->with([
                'block:id,property_id',
                'bookings' => fn ($query) => $query
                    ->select(['id', 'lot_id', 'status'])
                    ->whereIn('status', ['booking', 'process', 'akad', 'finish'])
                    ->with('sp3:id,booking_id,status')
                    ->latest('id'),
                'constructionProgresses' => fn ($query) => $query
                    ->select(['id', 'lot_id', 'stage_id', 'progress'])
                    ->whereNotNull('stage_id')
                    ->with('stage:id,property_id,max_progress,is_active'),
            ])
            ->get();
        $stages = ConstructionStage::query()
            ->where(function ($query) use ($dashboardLots): void {
                $query->whereIn(
                    'property_id',
                    $dashboardLots->pluck('block.property_id')->unique()->values()
                )->orWhereNull('property_id');
            })
            ->get(['id', 'property_id', 'max_progress', 'is_active']);
        $propertyStagePropertyIds = $stages
            ->whereNotNull('property_id')
            ->pluck('property_id')
            ->unique();
        $activeStages = $stages->where('is_active', true);
        $propertyStages = $activeStages
            ->whereNotNull('property_id')
            ->groupBy('property_id');
        $globalStages = $activeStages->whereNull('property_id');
        $statusByLot = $dashboardLots->mapWithKeys(function (Lot $lot) use ($propertyStagePropertyIds, $propertyStages, $globalStages): array {
            $requiredStages = $propertyStagePropertyIds->contains($lot->block->property_id)
                ? $propertyStages->get($lot->block->property_id, collect())
                : $globalStages;
            $progressByStage = $lot->constructionProgresses->keyBy('stage_id');
            $allStagesComplete = $requiredStages->isNotEmpty()
                && $requiredStages->every(function (ConstructionStage $stage) use ($progressByStage): bool {
                    $progress = $progressByStage->get($stage->id);

                    return $progress !== null
                        && (float) $progress->progress >= 100;
                });
            $booking = $lot->bookings->first();

            $status = match ($booking?->status) {
                'booking' => 'booked',
                'process' => $booking->sp3?->status === 'issued' ? 'sp3' : 'process',
                'akad' => 'akad',
                'finish' => $allStagesComplete ? 'finish' : 'akad',
                default => match ($lot->status) {
                    'blocked' => 'booked',
                    'finish' => $allStagesComplete ? 'finish' : 'akad',
                    default => $lot->status,
                },
            };

            return [$lot->id => $status];
        });
        $lotStatusCounts = $statusByLot->countBy();
        $physicalProgressByLot = $dashboardLots
            ->map(fn (Lot $lot): float => $lot->constructionProgressPercentage());

        $stats = [
            'total_properties' => Property::query()->when($propertyId, fn ($query) => $query->whereKey($propertyId))->count(),
            'total_blocks' => Block::query()
                ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
                ->when($blockId, fn ($query) => $query->whereKey($blockId))
                ->count(),
            'total_lots' => $dashboardLots->count(),

            'physical_progress' => round((float) $physicalProgressByLot->avg(), 2),
        ];

        foreach (['available', 'booked', 'process', 'sp3', 'akad', 'finish', 'cancelled'] as $status) {
            $stats[$status.'_lots'] = (int) $lotStatusCounts->get($status, 0);
        }

        $filterFinancialRecords = fn ($query) => $query
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->when($lotId, fn ($query) => $query->where(function ($query) use ($lotId): void {
                $query->where('lot_id', $lotId)
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }))
            ->when(! $lotId && $blockId, fn ($query) => $query->where(function ($query) use ($blockId): void {
                $query->whereIn('lot_id', Lot::query()->where('block_id', $blockId)->select('id'))
                    ->orWhere(fn ($query) => $query
                        ->whereNull('lot_id')
                        ->whereNull('facility_id'));
            }));
        $constructionBudget = Budget::query()
            ->where('purpose', 'pembangunan')
            ->whereNull('facility_id')
            ->tap($filterFinancialRecords)
            ->sum('amount');
        $constructionExpense = ExpenseTransaction::query()
            ->where('division', 'pembangunan')
            ->whereNull('facility_id')
            ->tap($filterFinancialRecords)
            ->sum('amount');
        $facilityBudgets = CommonFacility::query()
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->withSum([
                'budgets as pembangunan_budget_total' => fn ($query) => $query->where('purpose', 'pembangunan'),
            ], 'amount')
            ->get();
        $totalFacilityBudget = $facilityBudgets->sum(
            fn (CommonFacility $facility): float => (float) $facility->pembangunan_budget_total > 0
                ? (float) $facility->pembangunan_budget_total
                : (float) $facility->budget
        );
        $facilityExpense = ExpenseTransaction::query()
            ->where('division', 'pembangunan')
            ->whereNotNull('facility_id')
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->sum('amount');
        $constructionDeviation = (float) $constructionBudget - (float) $constructionExpense;
        $facilityDeviation = (float) $totalFacilityBudget - (float) $facilityExpense;
        $totalBudget = (float) $constructionBudget + (float) $totalFacilityBudget;
        $totalExpense = (float) $constructionExpense + (float) $facilityExpense;
        $totalBudgetDeviation = $totalBudget - $totalExpense;
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

        $lotStatusDefinitions = [
            'available' => ['label' => 'Tersedia', 'color' => '#15803d'],
            'booked' => ['label' => 'Booking', 'color' => '#2563eb'],
            'process' => ['label' => 'Proses', 'color' => '#ea580c'],
            'sp3' => ['label' => 'SP3', 'color' => '#ca8a04'],
            'akad' => ['label' => 'Akad', 'color' => '#1e3a8a'],
            'finish' => ['label' => 'Serah Terima Rumah', 'color' => '#059669'],
            'cancelled' => ['label' => 'Dibatalkan', 'color' => '#64748b'],
        ];
        $lotStatusTotal = $stats['total_lots'];
        $cumulativeStatusCount = 0;
        $lotStatusChart = collect($lotStatusDefinitions)
            ->map(function (array $statusData, string $status) use ($lotStatusCounts, $lotStatusTotal, &$cumulativeStatusCount): array {
                $value = (int) $lotStatusCounts->get($status, 0);
                $startPercent = $lotStatusTotal > 0 ? $cumulativeStatusCount / $lotStatusTotal * 100 : 0;
                $cumulativeStatusCount += $value;
                $endPercent = $lotStatusTotal > 0 ? $cumulativeStatusCount / $lotStatusTotal * 100 : 0;

                return [
                    'status' => $status,
                    'label' => $statusData['label'],
                    'color' => $statusData['color'],
                    'value' => $value,
                    'percent' => $lotStatusTotal > 0 ? round($value / $lotStatusTotal * 100, 1) : 0,
                    'start_percent' => $startPercent,
                    'end_percent' => $endPercent,
                ];
            })
            ->values();

        return view(
            'pembangunan.dashboard',
            compact(
                'stats',
                'properties',
                'selectedProperty',
                'lots',
                'sitePlanLots',
                'sitePlanAreas',
                'propertyId',
                'blockId',
                'lotId',
                'lotStatusChart',
                'lotStatusTotal',
                'constructionBudget',
                'constructionExpense',
                'constructionDeviation',
                'totalBudget',
                'totalFacilityBudget',
                'facilityExpense',
                'facilityDeviation',
                'totalExpense',
                'totalBudgetDeviation',
                'stageProgress',
                'facilityProgressChart',
            )
        );
    }
}
