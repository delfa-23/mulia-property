<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingLead;
use App\Models\MarketingLeadCall;
use App\Models\MarketingLeadSurvey;
use App\Services\MarketingLeadAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingLeadActivityController extends Controller
{
    public function storeCall(Request $request, MarketingLead $lead, MarketingLeadAccess $access): RedirectResponse
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat menerima aktivitas baru.');

        $validated = $request->validate([
            'called_at' => ['required', 'date', 'before_or_equal:now'],
            'outcome' => ['required', Rule::in(MarketingLeadCall::OUTCOMES)],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);
        $validated['performed_by'] = $request->user()->id;

        $call = $lead->calls()->create($validated);
        $lead->update([
            'last_contact_at' => $call->called_at->toDateString(),
            'last_progress' => $call->notes ?: 'Panggilan: '.str_replace('_', ' ', $call->outcome),
        ]);

        return back()->with('success', 'Log panggilan berhasil dicatat.');
    }

    public function storeSurvey(Request $request, MarketingLead $lead, MarketingLeadAccess $access): RedirectResponse
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat menerima aktivitas baru.');

        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'assigned_user_id' => $access->validateAssigneeRule($request->user()),
            'scheduled_at' => ['required', 'date'],
            'status' => ['required', Rule::in(MarketingLeadSurvey::STATUSES)],
            'result_notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);
        $validated['assigned_user_id'] = $access->assignedUserId(
            $request->user(),
            $validated['assigned_user_id'] ?? null,
        );
        $lead->surveys()->create($validated);

        return back()->with('success', 'Jadwal survei berhasil dicatat.');
    }

    public function updateSurvey(
        Request $request,
        MarketingLead $lead,
        MarketingLeadSurvey $survey,
        MarketingLeadAccess $access,
    ): RedirectResponse {
        $access->authorizeLead($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat menerima aktivitas baru.');
        abort_unless((int) $survey->lead_id === (int) $lead->id, 404);
        abort_unless(
            $access->canManageTeam($request->user())
            || $survey->assigned_user_id === null
            || (int) $survey->assigned_user_id === (int) $request->user()->id,
            403,
        );
        $validated = $request->validate([
            'status' => ['required', Rule::in(MarketingLeadSurvey::STATUSES)],
            'result_notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);
        $survey->update($validated);

        return back()->with('success', 'Status survei berhasil diperbarui.');
    }
}
