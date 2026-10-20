<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MarketingLead;
use App\Models\Property;
use App\Models\User;
use App\Services\MarketingLeadAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarketingLeadReportController extends Controller
{
    public function __invoke(Request $request, MarketingLeadAccess $access): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'category' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.']);
        }
        $user = $request->user();
        $marketingId = $filters['assigned_user_id'] ?? null;

        if (! $access->canManageTeam($user)) {
            $marketingId = $user->id;
        }

        $leads = MarketingLead::query()
            ->whereNull('archived_at')
            ->whereBetween('date_received', [$from, $to])
            ->when($marketingId, fn (Builder $query) => $query->where('assigned_user_id', $marketingId))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('category', $filters['category']))
            ->when(isset($filters['source']), fn (Builder $query) => $query->where('source', $filters['source']))
            ->when(isset($filters['property_id']), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->whereHas('surveys', fn (Builder $surveys) => $surveys->where('property_id', $filters['property_id']))
                        ->orWhereHas('bookings', fn (Builder $bookings) => $bookings->whereHas('lot.block', fn (Builder $blocks) => $blocks->where('property_id', $filters['property_id'])));
                });
            });
        $access->scopeLeads($leads, $user);

        $leadIds = (clone $leads)->select('marketing_leads.id');
        $totalLeads = (clone $leads)->count();
        $contactedLeads = (clone $leads)
            ->whereHas('calls', fn (Builder $calls) => $calls->whereBetween('called_at', [$from.' 00:00:00', $to.' 23:59:59']))
            ->count();
        $surveyedLeads = (clone $leads)
            ->whereHas('surveys', fn (Builder $surveys) => $surveys
                ->where('status', 'completed')
                ->whereBetween('scheduled_at', [$from.' 00:00:00', $to.' 23:59:59']))
            ->count();

        $bookingQuery = Booking::query()
            ->whereIn('lead_id', $leadIds)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('booking_date', [$from, $to])
            ->when(isset($filters['property_id']), fn (Builder $query) => $query->whereHas('lot.block', fn (Builder $blocks) => $blocks->where('property_id', $filters['property_id'])));
        $bookingCount = (clone $bookingQuery)->count();
        $bookedLeadCount = (clone $bookingQuery)->distinct()->count('lead_id');
        $akadBookingQuery = (clone $bookingQuery)->whereIn('status', ['akad', 'finish']);
        $akadBookingCount = (clone $akadBookingQuery)->count();

        $sourceCounts = (clone $leads)->selectRaw('source, COUNT(*) AS total')->groupBy('source')->orderBy('source')->get();
        $marketers = User::query()
            ->whereIn('role', ['staff_marketing', 'tl_marketing'])
            ->whereHas('division', fn (Builder $query) => $query->where('slug', 'marketing'))
            ->when($marketingId, fn (Builder $query) => $query->where('id', $marketingId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $performance = $marketers->map(function (User $marketer) use ($leads, $from, $to, $filters): array {
            $marketerLeads = (clone $leads)->where('assigned_user_id', $marketer->id);
            $marketerLeadIds = (clone $marketerLeads)->select('marketing_leads.id');
            $marketerBookings = Booking::query()
                ->whereIn('lead_id', $marketerLeadIds)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('booking_date', [$from, $to])
                ->when(isset($filters['property_id']), fn (Builder $query) => $query->whereHas('lot.block', fn (Builder $blocks) => $blocks->where('property_id', $filters['property_id'])));

            return [
                'user' => $marketer,
                'leads' => (clone $marketerLeads)->count(),
                'contacted' => (clone $marketerLeads)->whereHas('calls', fn (Builder $calls) => $calls->whereBetween('called_at', [$from.' 00:00:00', $to.' 23:59:59']))->count(),
                'surveyed' => (clone $marketerLeads)->whereHas('surveys', fn (Builder $surveys) => $surveys
                    ->where('status', 'completed')
                    ->whereBetween('scheduled_at', [$from.' 00:00:00', $to.' 23:59:59']))->count(),
                'bookings' => (clone $marketerBookings)->count(),
                'akad' => (clone $marketerBookings)->whereIn('status', ['akad', 'finish'])->count(),
            ];
        });

        return view('marketing.crm.report', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'teamMembers' => $access->canManageTeam($user)
                ? User::query()
                    ->whereIn('role', ['staff_marketing', 'tl_marketing'])
                    ->whereHas('division', fn (Builder $query) => $query->where('slug', 'marketing'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'categories' => MarketingLead::query()->select('category')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'categoryLabels' => MarketingLead::CATEGORY_LABELS,
            'sources' => MarketingLead::query()->select('source')->whereNotNull('source')->distinct()->orderBy('source')->pluck('source'),
            'sourceCounts' => $sourceCounts,
            'performance' => $performance,
            'metrics' => [
                'leads' => $totalLeads,
                'hot' => (clone $leads)->where('category', 'Hot')->count(),
                'contacted' => $contactedLeads,
                'surveyed' => $surveyedLeads,
                'bookings' => $bookingCount,
                'akad_bookings' => $akadBookingCount,
                'booked_leads' => $bookedLeadCount,
                'lead_to_booking_rate' => $totalLeads > 0 ? round($bookedLeadCount * 100 / $totalLeads, 1) : 0,
                'booking_to_akad_rate' => $bookingCount > 0 ? round($akadBookingCount * 100 / $bookingCount, 1) : 0,
            ],
        ]);
    }
}
