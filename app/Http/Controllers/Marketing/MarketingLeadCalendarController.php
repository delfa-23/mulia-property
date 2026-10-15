<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingLeadFollowUp;
use App\Models\MarketingLeadSurvey;
use App\Models\User;
use App\Services\MarketingLeadAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingLeadCalendarController extends Controller
{
    public function __invoke(Request $request, MarketingLeadAccess $access): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'in:survey,follow_up'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled,no_show,pending,in_progress'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $filters['month'] ?? now()->format('Y-m'))->startOfMonth();
        $calendarStart = $month->copy()->startOfWeek();
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek();
        $user = $request->user();
        $marketingId = $filters['assigned_user_id'] ?? null;

        if (! $access->canManageTeam($user)) {
            $marketingId = $user->id;
        }

        $leadScope = fn ($query) => $access->scopeLeads($query, $user)->whereNull('archived_at');
        $items = collect();

        if (! isset($filters['type']) || $filters['type'] === 'survey') {
            $surveys = MarketingLeadSurvey::query()
                ->with(['lead:id,name,assigned_user_id', 'assignedUser:id,name', 'property:id,name'])
                ->whereBetween('scheduled_at', [$calendarStart, $calendarEnd])
                ->whereHas('lead', fn ($query) => $query->whereNull('archived_at'))
                ->where(function ($query) use ($leadScope, $access, $user): void {
                    $query->whereHas('lead', $leadScope);

                    if (! $access->canManageTeam($user)) {
                        $query->orWhere('assigned_user_id', $user->id);
                    }
                })
                ->when($marketingId, fn ($query) => $query->where('assigned_user_id', $marketingId))
                ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
                ->get();

            foreach ($surveys as $survey) {
                $items->push([
                    'date' => $survey->scheduled_at,
                    'type' => 'survey',
                    'title' => 'Survei: '.($survey->property?->name ?? 'Perumahan'),
                    'lead' => $survey->lead->name,
                    'marketing' => $survey->assignedUser?->name ?? 'Belum ditugaskan',
                    'status' => $survey->status,
                    'url' => route('marketing.leads.show', $survey->lead).'#survey-'.$survey->id,
                ]);
            }
        }

        if (! isset($filters['type']) || $filters['type'] === 'follow_up') {
            $followUps = MarketingLeadFollowUp::query()
                ->with(['lead:id,name,assigned_user_id', 'assignedUser:id,name'])
                ->whereBetween('due_at', [$calendarStart, $calendarEnd])
                ->whereHas('lead', fn ($query) => $query->whereNull('archived_at'))
                ->where(function ($query) use ($leadScope, $access, $user): void {
                    $query->whereHas('lead', $leadScope);

                    if (! $access->canManageTeam($user)) {
                        $query->orWhere('assigned_user_id', $user->id);
                    }
                })
                ->when($marketingId, fn ($query) => $query->where('assigned_user_id', $marketingId))
                ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
                ->get();

            foreach ($followUps as $followUp) {
                $items->push([
                    'date' => $followUp->due_at,
                    'type' => 'follow_up',
                    'title' => $followUp->title,
                    'lead' => $followUp->lead->name,
                    'marketing' => $followUp->assignedUser?->name ?? 'Belum ditugaskan',
                    'status' => $followUp->status,
                    'url' => route('marketing.leads.show', $followUp->lead).'#follow-up-'.$followUp->id,
                ]);
            }
        }

        $calendarDays = collect();
        for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day->addDay()) {
            $calendarDays->push([
                'date' => $day->copy(),
                'inMonth' => $day->month === $month->month,
                'items' => $items->filter(fn (array $item): bool => $item['date']->isSameDay($day))->values(),
            ]);
        }

        return view('marketing.crm.calendar', [
            'calendarDays' => $calendarDays,
            'activities' => $items->sortBy(fn (array $item) => $item['date'])->values(),
            'month' => $month,
            'teamMembers' => $access->canManageTeam($user)
                ? User::query()
                    ->whereIn('role', ['staff_marketing', 'tl_marketing'])
                    ->whereHas('division', fn ($query) => $query->where('slug', 'marketing'))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'filters' => $filters,
        ]);
    }
}
