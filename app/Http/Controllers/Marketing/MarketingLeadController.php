<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingLead;
use App\Models\Property;
use App\Models\Sales;
use App\Models\User;
use App\Services\MarketingLeadAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketingLeadController extends Controller
{
    public function index(Request $request, MarketingLeadAccess $access): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(MarketingLead::CATEGORIES)],
            'source' => ['nullable', Rule::in(MarketingLead::SOURCES)],
            'sales_id' => ['nullable', 'integer', 'exists:sales,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'archived' => ['nullable', 'boolean'],
        ]);

        $query = $access->scopeLeads(MarketingLead::query(), $request->user());
        $query->when(
            $request->boolean('archived'),
            fn (Builder $query): Builder => $query->whereNotNull('archived_at'),
            fn (Builder $query): Builder => $query->whereNull('archived_at'),
        );

        $leads = $query
            ->with(['sales:id,name', 'assignedUser:id,name', 'latestBooking'])
            ->withCount([
                'bookings as akad_bookings_count' => fn (Builder $query): Builder => $query
                    ->whereIn('status', ['akad', 'finish']),
            ])
            ->when($request->filled('search'), function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('domicile', 'like', $like);
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source): Builder => $query->where('source', $source))
            ->when($filters['sales_id'] ?? null, fn (Builder $query, int $salesId): Builder => $query->where('sales_id', $salesId))
            ->when(
                $access->canManageTeam($request->user()) && isset($filters['assigned_user_id']),
                fn (Builder $query): Builder => $query->where('assigned_user_id', $filters['assigned_user_id']),
            )
            ->latest('date_received')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $visibleLeads = $access->scopeLeads(MarketingLead::query(), $request->user())
            ->whereNull('archived_at');

        return view('marketing.leads.index', [
            'leads' => $leads,
            'categories' => MarketingLead::CATEGORIES,
            'sources' => MarketingLead::SOURCES,
            'salesOptions' => Sales::query()->orderBy('name')->get(['id', 'name']),
            'teamMembers' => $access->canManageTeam($request->user()) ? $this->marketingUsers() : collect(),
            'totalLeads' => (clone $visibleLeads)->count(),
            'hotLeads' => (clone $visibleLeads)->where('category', 'Hot')->count(),
            'akadLeads' => (clone $visibleLeads)
                ->whereHas('bookings', fn (Builder $query): Builder => $query->whereIn('status', ['akad', 'finish']))
                ->count(),
            'showArchived' => $request->boolean('archived'),
        ]);
    }

    public function create(Request $request, MarketingLeadAccess $access): View
    {
        return view('marketing.leads.create', $this->formData(new MarketingLead, $request->user(), $access));
    }

    public function store(Request $request, MarketingLeadAccess $access): RedirectResponse
    {
        $validated = $this->validated($request, $access);
        if (! $access->canManageTeam($request->user())) {
            $validated['assigned_user_id'] = $request->user()->id;
        }
        MarketingLead::create($validated);

        return redirect()->route('marketing.leads.index')->with('success', 'Prospek berhasil ditambahkan.');
    }

    public function show(Request $request, MarketingLead $lead, MarketingLeadAccess $access): View
    {
        $access->authorizeLead($request->user(), $lead);
        $lead->load([
            'sales:id,name',
            'assignedUser:id,name',
            'bookings.lot.block.property',
            'bookings.customer',
            'calls.performer:id,name',
            'surveys.property:id,name',
            'surveys.assignedUser:id,name',
            'followUps.assignedUser:id,name',
            'followUps.histories.changedBy:id,name',
        ]);

        return view('marketing.leads.show', [
            'lead' => $lead,
            'calls' => $lead->calls,
            'surveys' => $lead->surveys,
            'followUps' => $lead->followUps,
            'teamMembers' => $access->canManageTeam($request->user()) ? $this->marketingUsers() : collect(),
            'canManageTeam' => $access->canManageTeam($request->user()),
            'canManageLead' => $access->canManageLead($request->user(), $lead),
            'currentUserId' => $request->user()->id,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, MarketingLead $lead, MarketingLeadAccess $access): View
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat diubah.');

        return view('marketing.leads.edit', $this->formData($lead, $request->user(), $access));
    }

    public function update(Request $request, MarketingLead $lead, MarketingLeadAccess $access): RedirectResponse
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        abort_if($lead->isArchived(), 409, 'Prospek arsip tidak dapat diubah.');
        $validated = $this->validated($request, $access);
        if (! $access->canManageTeam($request->user())) {
            $validated['assigned_user_id'] = $lead->assigned_user_id ?? $request->user()->id;
        }
        $lead->update($validated);

        return redirect()->route('marketing.leads.show', $lead)->with('success', 'Prospek berhasil diperbarui.');
    }

    public function destroy(Request $request, MarketingLead $lead, MarketingLeadAccess $access): RedirectResponse
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        $lead->update(['archived_at' => now()]);

        return redirect()->route('marketing.leads.index')->with('success', 'Prospek berhasil diarsipkan.');
    }

    public function restore(Request $request, MarketingLead $lead, MarketingLeadAccess $access): RedirectResponse
    {
        $access->authorizeLeadOwner($request->user(), $lead);
        abort_unless($access->canManageTeam($request->user()), 403);
        $lead->update(['archived_at' => null]);

        return redirect()->route('marketing.leads.show', $lead)->with('success', 'Prospek berhasil dipulihkan.');
    }

    /** @return array<string, mixed> */
    private function formData(MarketingLead $lead, User $user, MarketingLeadAccess $access): array
    {
        return [
            'lead' => $lead,
            'categories' => MarketingLead::CATEGORIES,
            'categoryLabels' => MarketingLead::CATEGORY_LABELS,
            'sources' => MarketingLead::SOURCES,
            'salesOptions' => Sales::query()->orderBy('name')->get(['id', 'name']),
            'teamMembers' => $access->canManageTeam($user) ? $this->marketingUsers() : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, MarketingLeadAccess $access): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'date_received' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'domicile' => ['nullable', 'string', 'max:255'],
            'sales_id' => ['nullable', 'integer', 'exists:sales,id'],
            'category' => ['required', Rule::in(MarketingLead::CATEGORIES)],
            'source' => ['required', Rule::in(MarketingLead::SOURCES)],
            'last_progress' => ['nullable', 'string'],
            'last_contact_at' => ['nullable', 'date'],
        ];
        $rules['assigned_user_id'] = $access->validateAssigneeRule($request->user());

        return $request->validate($rules);
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
