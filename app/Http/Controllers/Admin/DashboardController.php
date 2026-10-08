<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\ConstructionProgress;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\Lot;
use App\Models\ProgressReport;
use App\Models\Property;
use App\Models\WeeklyReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
        ]);

        $propertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;
        $selectedBlock = null;

        $lotSelectionQuery = Lot::query()
            ->with('block')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)));
        $selectedLot = isset($validated['lot_id']) ? $lotSelectionQuery->find($validated['lot_id']) : null;

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
        $filterLotRelations = fn ($query) => $query->whereHas('lot', $filterLots);
        $filterFinancialRecords = fn ($query) => $query
            ->when($lotId, fn ($query) => $query->where('lot_id', $lotId))
            ->when($blockId && ! $lotId, fn ($query) => $query->whereHas('lot', fn ($query) => $query->where('block_id', $blockId)))
            ->when($propertyId && ! $blockId, fn ($query) => $query->where('property_id', $propertyId));

        $properties = Property::query()->orderBy('name')->get();
        $lots = Lot::query()
            ->with('block')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->orderBy('lot_number')
            ->get();

        $lotStats = $filterLots(Lot::query());
        $bookingStats = $filterLotRelations(Booking::query());
        $budgetStats = $filterFinancialRecords(Budget::query());
        $incomeStats = $filterFinancialRecords(IncomeTransaction::query());
        $expenseStats = $filterFinancialRecords(ExpenseTransaction::query());
        $physicalProgressByLot = ConstructionProgress::query()
            ->whereNotNull('stage_id')
            ->when($propertyId, $filterLotRelations)
            ->select('lot_id')
            ->selectRaw('MAX(progress) as physical_progress')
            ->groupBy('lot_id')
            ->get();

        $stats = [

            // =========================
            // MASTER PROJECT
            // =========================
            'total_properties' => Property::query()->when($propertyId, fn ($query) => $query->whereKey($propertyId))->count(),

            'total_blocks' => Block::query()
                ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
                ->when($blockId, fn ($query) => $query->whereKey($blockId))
                ->count(),

            'total_lots' => (clone $lotStats)->count(),

            // =========================
            // STATUS KAVLING
            // =========================
            'available_lots' => (clone $lotStats)->where(
                'status',
                'available'
            )->count(),

            'booked_lots' => (clone $lotStats)->where(
                'status',
                'booked'
            )->count(),

            'blocked_lots' => (clone $lotStats)->where(
                'status',
                'blocked'
            )->count(),

            'process_lots' => (clone $lotStats)->where(
                'status',
                'process'
            )->count(),

            'akad_lots' => (clone $lotStats)->where(
                'status',
                'akad'
            )->count(),

            'finish_lots' => (clone $lotStats)->where(
                'status',
                'finish'
            )->count(),

            // =========================
            // BOOKING
            // =========================
            'total_bookings' => (clone $bookingStats)->where(
                'status',
                '!=',
                'cancelled'
            )->count(),

            'process_bookings' => (clone $bookingStats)->where(
                'status',
                'process'
            )->count(),

            'akad_bookings' => (clone $bookingStats)->where(
                'status',
                'akad'
            )->count(),

            'finish_bookings' => (clone $bookingStats)->where(
                'status',
                'finish'
            )->count(),

            // =========================
            // KEUANGAN
            // =========================
            'total_income' => (clone $incomeStats)->sum('amount'),

            'total_expense' => (clone $expenseStats)->sum('amount'),

            // =========================
            // ALERT & REPORT
            // =========================
            'active_alerts' => Alert::query()
                ->where('is_resolved', false)
                ->when($propertyId, $filterLotRelations)
                ->count(),

            'pending_reports' => ProgressReport::whereIn(
                'status',
                [
                    'submitted',
                    'revision',
                ]
            )->count(),

            'physical_progress' => round((float) $physicalProgressByLot->avg('physical_progress'), 2),
            'facility_progress' => round((float) CommonFacility::query()
                ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
                ->get()
                ->avg(fn (CommonFacility $facility): float => $facility->progress), 2),
            'total_budget' => (clone $budgetStats)->sum('amount'),
            'deviation' => (clone $budgetStats)->sum('amount') - (clone $expenseStats)->sum('amount'),
            'weekly_reports' => WeeklyReport::query()->latest()->limit(5)->get(),
        ];

        $lotStatusDefinitions = [
            'available' => ['label' => 'Tersedia', 'color' => '#009851'],
            'booked' => ['label' => 'Booking', 'color' => '#3b82f6'],
            'process' => ['label' => 'Proses Berkas', 'color' => '#ff9100'],
            'akad' => ['label' => 'Akad', 'color' => '#6366f1'],
            'finish' => ['label' => 'Finish', 'color' => '#10b981'],
            'blocked' => ['label' => 'Blocked', 'color' => '#64748b'],
            'cancelled' => ['label' => 'Dibatalkan', 'color' => '#ff1100'],
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

        $gradientStops = [];
        $gradientPosition = 0.0;

        foreach ($lotStatusChart as $statusData) {
            if ($statusData['value'] === 0 || $lotStatusTotal === 0) {
                continue;
            }

            $nextPosition = $gradientPosition + ($statusData['value'] / $lotStatusTotal * 360);
            $gradientStops[] = sprintf('%s %.2fdeg %.2fdeg', $statusData['color'], $gradientPosition, $nextPosition);
            $gradientPosition = $nextPosition;
        }

        $lotStatusGradient = $gradientStops === []
            ? 'conic-gradient(#e2e8f0 0deg 360deg)'
            : 'conic-gradient(from -90deg, '.implode(', ', $gradientStops).')';

        $monthlyFinancialChart = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($incomeStats, $expenseStats): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);
                $monthRange = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M Y'),
                    'income' => (float) (clone $incomeStats)->whereBetween('transaction_date', $monthRange)->sum('amount'),
                    'expense' => (float) (clone $expenseStats)->whereBetween('transaction_date', $monthRange)->sum('amount'),
                ];
            })
            ->values();
        $monthlyFinancialChartMax = max(
            1,
            (float) $monthlyFinancialChart->max(fn (array $month): float => max($month['income'], $month['expense']))
        );

        $stageProgress = ConstructionProgress::query()
            ->when($propertyId, $filterLotRelations)
            ->select('stage_id')
            ->selectRaw('AVG(progress) as average_progress')
            ->groupBy('stage_id')
            ->with('stage:id,name')
            ->get()
            ->filter(fn (ConstructionProgress $progress): bool => $progress->stage !== null)
            ->map(fn (ConstructionProgress $progress): array => [
                'name' => $progress->stage->name,
                'progress' => round((float) $progress->average_progress, 2),
            ])
            ->sortBy('name')
            ->values();

        $recentBookings = $filterLotRelations(Booking::query())
            ->with(['customer', 'lot.block.property'])
            ->where('status', '!=', 'cancelled')
            ->latest('booking_date')
            ->latest('id')
            ->limit(6)
            ->get();

        $recentProgress = ConstructionProgress::query()
            ->with(['lot.block.property', 'stage', 'updatedBy'])
            ->when($propertyId, $filterLotRelations)
            ->latest()
            ->limit(6)
            ->get();

        $recentAlerts = Alert::query()
            ->with(['lot.block.property', 'createdBy'])
            ->where('is_resolved', false)
            ->when($propertyId, $filterLotRelations)
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'properties',
            'lots',
            'propertyId',
            'blockId',
            'lotId',
            'lotStatusChart',
            'lotStatusTotal',
            'lotStatusGradient',
            'monthlyFinancialChart',
            'monthlyFinancialChartMax',
            'stageProgress',
            'recentBookings',
            'recentProgress',
            'recentAlerts',
        ));
    }
}
