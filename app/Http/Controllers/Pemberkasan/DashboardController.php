<?php

namespace App\Http\Controllers\Pemberkasan;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'workflow_property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'workflow_lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'workflow' => ['nullable', 'string', Rule::in([
                'total_bookings',
                'bi_checking',
                'incomplete_documents',
                'complete_documents',
                'bank_processing',
                'sp3_issued',
                'waiting_akad',
                'finish',
                'revision',
            ])],
        ]);
        $propertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;
        $workflowPropertyId = isset($validated['workflow_property_id']) ? (int) $validated['workflow_property_id'] : null;
        $workflowLotId = isset($validated['workflow_lot_id']) ? (int) $validated['workflow_lot_id'] : null;
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
        $filterBookings = fn ($query) => $query->whereHas('lot', $filterLots);

        $properties = Property::query()->orderBy('name')->get();
        $selectedProperty = $properties->firstWhere('id', $propertyId);
        $blocks = Block::query()
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->orderBy('name')
            ->get();
        $lots = Lot::query()
            ->with('block.property')
            ->when($propertyId, fn ($query) => $query->whereHas('block', fn ($query) => $query->where('property_id', $propertyId)))
            ->when($blockId, fn ($query) => $query->where('block_id', $blockId))
            ->orderBy('lot_number')
            ->get();
        $workflowFilterLots = $propertyId === null && $blockId === null
            ? $lots
            : Lot::query()
                ->with([
                    'block:id,property_id,name',
                    'block.property:id,name',
                ])
                ->orderBy('lot_number')
                ->get(['id', 'block_id', 'lot_number']);
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

        $workflowDefinitions = [
            'total_bookings' => ['label' => 'Booking Aktif', 'card_label' => 'Total Booking', 'color' => '#10233d'],
            'bi_checking' => ['label' => 'BI Checking', 'color' => '#ff9100'],
            'incomplete_documents' => ['label' => 'Berkas Belum Lengkap', 'color' => '#eb5120'],
            'complete_documents' => ['label' => 'Berkas Lengkap', 'color' => '#009851'],
            'bank_processing' => ['label' => 'Proses Bank', 'color' => '#3b82f6'],
            'sp3_issued' => ['label' => 'SP3 Terbit', 'color' => '#1e3a5f'],
            'waiting_akad' => ['label' => 'Akad', 'color' => '#ff9100'],
            'finish' => ['label' => 'Serah Terima Rumah', 'color' => '#10b981'],
            'revision' => ['label' => 'Revisi', 'color' => '#ef4444'],
        ];
        $workflowBookingsQuery = function (string $workflow, bool $applyDashboardFilters = true) use ($filterBookings): Builder {
            $bookings = ($applyDashboardFilters ? $filterBookings(Booking::query()) : Booking::query())
                ->where('status', '!=', 'cancelled');

            return match ($workflow) {
                'total_bookings' => $bookings,
                'bi_checking' => $bookings->whereHas('documentProcess', fn ($query) => $query->where('bi_checking_status', 'processing')),
                'incomplete_documents' => $bookings->whereHas('documentProcess', fn ($query) => $query->where('status', 'incomplete')),
                'complete_documents' => $bookings->whereHas('documentProcess', fn ($query) => $query->where('status', 'complete')),
                'bank_processing' => $bookings->whereHas('bankProcess', fn ($query) => $query->whereIn('status', ['submitted', 'processing'])),
                'sp3_issued' => $bookings->whereHas('sp3', fn ($query) => $query->where('status', 'issued')),
                'waiting_akad' => $bookings->whereHas('akadSchedule', fn ($query) => $query->where('status', 'scheduled')),
                'finish' => $bookings->where('status', 'finish'),
                'revision' => $bookings->whereHas('documentProcess', fn ($query) => $query
                    ->where('status', 'revision')
                    ->orWhere('bi_checking_status', 'revision')),
            };
        };
        $stats = collect($workflowDefinitions)
            ->mapWithKeys(fn (array $definition, string $workflow): array => [
                $workflow => $workflowBookingsQuery($workflow)->count(),
            ])
            ->all();
        $workflowChart = collect($workflowDefinitions)
            ->map(fn (array $workflow, string $key): array => [
                'key' => $key,
                'label' => $workflow['label'],
                'card_label' => $workflow['card_label'] ?? $workflow['label'],
                'color' => $workflow['color'],
                'value' => $stats[$key],
            ])
            ->values();
        $workflowChartMax = max(1, (int) $workflowChart->max('value'));
        $selectedWorkflow = $validated['workflow'] ?? null;
        $hasWorkflowFilters = $workflowPropertyId !== null || $workflowLotId !== null;
        $workflowBookingsQueryBuilder = $selectedWorkflow
            ? $workflowBookingsQuery($selectedWorkflow, ! $hasWorkflowFilters)
            : null;

        if ($workflowBookingsQueryBuilder !== null && $workflowPropertyId !== null) {
            $workflowBookingsQueryBuilder->whereHas('lot.block', fn ($query) => $query->where('property_id', $workflowPropertyId));
        }

        if ($workflowBookingsQueryBuilder !== null && $workflowLotId !== null) {
            $workflowBookingsQueryBuilder->whereHas('lot', fn ($query) => $query->whereKey($workflowLotId));
        }

        $workflowBookings = $workflowBookingsQueryBuilder
            ? $workflowBookingsQueryBuilder
                ->with(['customer', 'sales', 'lot.block.property', 'documentProcess', 'bankProcess', 'sp3', 'akadSchedule'])
                ->latest()
                ->paginate(15)
                ->withQueryString()
            : null;

        $monthlyBookingChart = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($filterBookings): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);
                $count = $filterBookings(Booking::query())
                    ->where('status', '!=', 'cancelled')
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

        $bookings = $filterBookings(Booking::query())
            ->with(['customer', 'lot.block.property', 'documentProcess', 'bankProcess', 'sp3', 'akadSchedule'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(10)
            ->get();

        return view('pemberkasan.dashboard', compact(
            'stats',
            'bookings',
            'properties',
            'selectedProperty',
            'blocks',
            'lots',
            'workflowFilterLots',
            'sitePlanLots',
            'sitePlanAreas',
            'propertyId',
            'blockId',
            'lotId',
            'workflowPropertyId',
            'workflowLotId',
            'workflowChart',
            'workflowChartMax',
            'workflowBookings',
            'selectedWorkflow',
            'monthlyBookingChart',
            'monthlyBookingChartMax',
        ));
    }
}
