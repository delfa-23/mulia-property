<?php

namespace App\Http\Controllers\Pemberkasan;

use App\Http\Controllers\Controller;
use App\Models\AkadSchedule;
use App\Models\BankProcess;
use App\Models\Block;
use App\Models\Booking;
use App\Models\DocumentProcess;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Sp3;
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
        $filterBookings = fn ($query) => $query->whereHas('lot', $filterLots);
        $filterBookingProcesses = fn ($query) => $query->whereHas('booking', $filterBookings);

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

        $stats = [
            'total_bookings' => $filterBookings(Booking::query())->where('status', '!=', 'cancelled')->count(),
            'incomplete_documents' => $filterBookingProcesses(DocumentProcess::query())->where('status', 'incomplete')->count(),
            'complete_documents' => $filterBookingProcesses(DocumentProcess::query())->where('status', 'complete')->count(),
            'bi_checking' => $filterBookingProcesses(DocumentProcess::query())->where('bi_checking_status', 'processing')->count(),
            'bank_processing' => $filterBookingProcesses(BankProcess::query())->whereIn('status', ['submitted', 'processing'])->count(),
            'bank_approved' => $filterBookingProcesses(BankProcess::query())->where('status', 'approved')->count(),
            'sp3_issued' => $filterBookingProcesses(Sp3::query())->where('status', 'issued')->count(),
            'waiting_akad' => $filterBookingProcesses(AkadSchedule::query())->where('status', 'scheduled')->count(),
            'finish' => $filterBookings(Booking::query())->where('status', 'finish')->count(),
            'revision' => $filterBookingProcesses(DocumentProcess::query())
                ->where(fn ($query) => $query->where('status', 'revision')->orWhere('bi_checking_status', 'revision'))
                ->count(),
        ];

        $workflowDefinitions = [
            'total_bookings' => ['label' => 'Booking Aktif', 'color' => '#10233d'],
            'incomplete_documents' => ['label' => 'Berkas Belum Lengkap', 'color' => '#eb5120'],
            'complete_documents' => ['label' => 'Berkas Lengkap', 'color' => '#009851'],
            'bi_checking' => ['label' => 'BI Checking', 'color' => '#ff9100'],
            'bank_processing' => ['label' => 'Proses Bank', 'color' => '#3b82f6'],
            'bank_approved' => ['label' => 'Bank Disetujui', 'color' => '#009851'],
            'sp3_issued' => ['label' => 'SP3 Terbit', 'color' => '#1e3a5f'],
            'waiting_akad' => ['label' => 'Menunggu Akad', 'color' => '#ff9100'],
            'finish' => ['label' => 'Finish', 'color' => '#10b981'],
            'revision' => ['label' => 'Revisi', 'color' => '#ef4444'],
        ];
        $workflowChart = collect($workflowDefinitions)
            ->map(fn (array $workflow, string $key): array => [
                'key' => $key,
                'label' => $workflow['label'],
                'color' => $workflow['color'],
                'value' => $stats[$key],
            ])
            ->values();
        $workflowChartMax = max(1, (int) $workflowChart->max('value'));

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
            'blocks',
            'lots',
            'propertyId',
            'blockId',
            'lotId',
            'workflowChart',
            'workflowChartMax',
            'monthlyBookingChart',
            'monthlyBookingChartMax',
        ));
    }
}
