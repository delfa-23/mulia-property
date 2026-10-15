<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingLead;
use App\Models\MarketingLeadFollowUp;
use App\Models\MarketingLeadFollowUpHistory;
use App\Models\User;
use App\Services\MarketingLeadAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketingLeadFollowUpController extends Controller
{
    public function index(Request $request, MarketingLeadAccess $access): View
    {
        $filters = $request->validate([
            'bucket' => ['nullable', Rule::in(['today', 'upcoming', 'overdue', 'completed'])],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $bucket = $filters['bucket'] ?? 'today';
        $query = MarketingLeadFollowUp::query()
            ->whereHas('lead', fn (Builder $query): Builder => $query->whereNull('archived_at'))
            ->where(function (Builder $query) use ($access, $request): void {
                $query->whereHas('lead', fn (Builder $leadQuery): Builder => $access->scopeLeads(
                    $leadQuery,
                    $request->user(),
                ));

                if (! $access->canManageTeam($request->user())) {
                    $query->orWhere('assigned_user_id', $request->user()->id);
                }
            })
            ->with(['lead:id,name,phone', 'assignedUser:id,name', 'histories.changedBy:id,name']);

        if ($access->canManageTeam($request->user()) && isset($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        } elseif (! $access->canManageTeam($request->user())) {
            $query->where(function (Builder $query) use ($request): void {
                $query->where('assigned_user_id', $request->user()->id)
                    ->orWhereNull('assigned_user_id');
            });
        }

        match ($bucket) {
            'overdue' => $query->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now()),
            'upcoming' => $query->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '>', now()->endOfDay()),
            'completed' => $query->where('status', 'completed'),
            default => $query->whereNotIn('status', ['completed', 'cancelled'])
                ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
        };

        return view('marketing.follow-ups.index', [
            'followUps' => $query->orderBy('due_at')->paginate(20)->withQueryString(),
            'bucket' => $bucket,
            'teamMembers' => $access->canManageTeam($request->user()) ? $this->marketingUsers() : collect(),
        ]);
    }

    public function store(Request $request, MarketingLead $lead, MarketingLeadAccess $access): RedirectResponse
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat menerima aktivitas baru.');

        $validated = $request->validate([
            'assigned_user_id' => $access->validateAssigneeRule($request->user()),
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'priority' => ['required', Rule::in(MarketingLeadFollowUp::PRIORITIES)],
            'result_notes' => ['nullable', 'string'],
        ]);
        $validated['assigned_user_id'] = $access->assignedUserId(
            $request->user(),
            $validated['assigned_user_id'] ?? null,
        );
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'pending';

        DB::transaction(function () use ($lead, $validated, $request): void {
            $followUp = $lead->followUps()->create($validated);
            MarketingLeadFollowUpHistory::create([
                'follow_up_id' => $followUp->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => $request->user()->id,
                'notes' => 'Tindak lanjut dibuat.',
                'changed_at' => now(),
            ]);
        });

        return back()->with('success', 'Tugas follow-up berhasil dibuat.');
    }

    public function update(
        Request $request,
        MarketingLead $lead,
        MarketingLeadFollowUp $followUp,
        MarketingLeadAccess $access,
    ): RedirectResponse {
        $access->authorizeLead($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat menerima aktivitas baru.');
        abort_unless((int) $followUp->lead_id === (int) $lead->id, 404);
        abort_unless(
            $access->canManageTeam($request->user())
            || $followUp->assigned_user_id === null
            || (int) $followUp->assigned_user_id === (int) $request->user()->id,
            403,
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(MarketingLeadFollowUp::STATUSES)],
            'result_notes' => ['nullable', 'string'],
        ]);
        $previousStatus = $followUp->status;

        DB::transaction(function () use ($followUp, $validated, $previousStatus, $request): void {
            $followUp->update([
                ...$validated,
                'completed_at' => $validated['status'] === 'completed' ? ($followUp->completed_at ?? now()) : null,
            ]);

            if ($validated['status'] !== $previousStatus) {
                MarketingLeadFollowUpHistory::create([
                    'follow_up_id' => $followUp->id,
                    'from_status' => $previousStatus,
                    'to_status' => $validated['status'],
                    'changed_by' => $request->user()->id,
                    'notes' => $validated['result_notes'] ?? null,
                    'changed_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Follow-up berhasil diperbarui.');
    }

    /** @return Collection<int, User> */
    private function marketingUsers(): Collection
    {
        return User::query()
            ->whereIn('role', ['staff_marketing', 'tl_marketing'])
            ->whereHas('division', fn (Builder $query): Builder => $query->where('slug', 'marketing'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
