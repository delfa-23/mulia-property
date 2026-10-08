<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\ExpenseTransaction;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $selectedLotQuery = Lot::query()
            ->with('block')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($selectedBlock, fn ($query) => $query->where('block_id', $selectedBlock->id));
        $selectedLot = isset($validated['lot_id']) ? $selectedLotQuery->find($validated['lot_id']) : null;

        if ($selectedLot !== null) {
            $selectedBlock = $selectedLot->block;
            $propertyId = $selectedBlock->property_id;
        }

        $blockId = $selectedBlock?->id;
        $lotId = $selectedLot?->id;
        $activeStatuses = ['booking', 'process', 'akad', 'finish'];

        $filterLots = fn ($query) => $query
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($blockId, fn ($query) => $query->where('block_id', $blockId))
            ->when($lotId, fn ($query) => $query->whereKey($lotId));
        $filterBookingsByProperty = fn ($query) => $query->whereHas('lot', $filterLots);
        $filterPropertyRecords = fn ($query) => $query
            ->when($lotId, fn ($query) => $query->where('lot_id', $lotId))
            ->when($blockId && ! $lotId, fn ($query) => $query->whereHas('lot', fn ($query) => $query->where('block_id', $blockId)))
            ->when($propertyId && ! $blockId, fn ($query) => $query->where('property_id', $propertyId));

        $bookings = $filterBookingsByProperty(Booking::query());
        $lots = $filterLots(Lot::query());
        $stats = [
            'total_customers' => Customer::query()
                ->when($propertyId, fn ($query) => $query->whereHas('bookings', $filterBookingsByProperty))
                ->count(),
            'active_bookings' => (clone $bookings)->whereIn('status', $activeStatuses)->count(),
            'process_bookings' => (clone $bookings)->where('status', 'process')->count(),
            'akad_bookings' => (clone $bookings)->where('status', 'akad')->count(),
            'finish_bookings' => (clone $bookings)->where('status', 'finish')->count(),
            'available_lots' => (clone $lots)->where('status', 'available')->count(),
            'blocked_lots' => (clone $lots)->where('status', 'blocked')->count(),
            'booked_lots' => (clone $lots)->whereIn('status', ['booked', 'process', 'akad', 'finish'])->count(),
        ];

        $bookingStatusDefinitions = [
            'booking' => ['label' => 'Booking', 'color' => '#eb5120'],
            'process' => ['label' => 'Proses Berkas', 'color' => '#ff9100'],
            'akad' => ['label' => 'Akad', 'color' => '#1e3a5f'],
            'finish' => ['label' => 'Finish', 'color' => '#009851'],
            'cancelled' => ['label' => 'Dibatalkan', 'color' => '#ef4444'],
        ];
        $bookingStatusChart = collect($bookingStatusDefinitions)
            ->map(fn (array $statusData, string $status): array => [
                'status' => $status,
                'label' => $statusData['label'],
                'color' => $statusData['color'],
                'value' => (clone $bookings)->where('status', $status)->count(),
            ])
            ->values();
        $monthlyBookingChart = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($filterBookingsByProperty, $activeStatuses): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);
                $count = $filterBookingsByProperty(Booking::query())
                    ->whereIn('status', $activeStatuses)
                    ->whereBetween('booking_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                    ->count();

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M Y'),
                    'bookings' => $count,
                ];
            })
            ->values();
        $monthlyBookingChartMax = max(1, (int) $monthlyBookingChart->max('bookings'));

        $recentBookings = $filterBookingsByProperty(Booking::query())
            ->with(['lot.block.property', 'customer', 'sales'])
            ->latest()
            ->limit(8)
            ->get();
        $salesPerformance = Sales::query()
            ->where('is_active', true)
            ->withCount(['bookings as active_sales_bookings' => fn ($query) => $filterBookingsByProperty($query->whereIn('status', $activeStatuses))])
            ->orderByDesc('active_sales_bookings')
            ->get();
        $salesPerformanceChart = $salesPerformance
            ->take(6)
            ->map(fn (Sales $sales): array => [
                'name' => $sales->name,
                'bookings' => (int) $sales->active_sales_bookings,
            ])
            ->values();
        $salesPerformanceChartMax = max(1, (int) $salesPerformanceChart->max('bookings'));

        $financialStats = [
            'total_budget' => $filterPropertyRecords(Budget::query())->sum('amount'),
            'total_expense' => $filterPropertyRecords(ExpenseTransaction::query())->sum('amount'),
        ];
        $financialStats['deviation'] = $financialStats['total_budget'] - $financialStats['total_expense'];

        $recentExpenses = $filterPropertyRecords(ExpenseTransaction::query())
            ->with(['property', 'lot', 'facility', 'createdBy'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(8)
            ->get();
        $expenseByCategory = $filterPropertyRecords(ExpenseTransaction::query())
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

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

        return view('marketing.dashboard', compact(
            'stats',
            'properties',
            'blocks',
            'lots',
            'propertyId',
            'blockId',
            'lotId',
            'recentBookings',
            'salesPerformance',
            'financialStats',
            'recentExpenses',
            'expenseByCategory',
            'bookingStatusChart',
            'monthlyBookingChart',
            'monthlyBookingChartMax',
            'salesPerformanceChart',
            'salesPerformanceChartMax',
        ));
    }
}
