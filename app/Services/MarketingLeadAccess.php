<?php

namespace App\Services;

use App\Models\Division;
use App\Models\MarketingLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class MarketingLeadAccess
{
    /** @param Builder<MarketingLead> $query
     * @return Builder<MarketingLead>
     */
    public function scopeLeads(Builder $query, User $user): Builder
    {
        if ($this->canManageTeam($user)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('assigned_user_id', $user->id)
                ->orWhereNull('assigned_user_id');
        });
    }

    public function canManageTeam(User $user): bool
    {
        return $user->isAdmin() || $user->role === 'tl_marketing';
    }

    public function authorizeLead(User $user, MarketingLead $lead): void
    {
        abort_unless($this->canAccessLead($user, $lead), 403);
    }

    public function authorizeLeadOwner(User $user, MarketingLead $lead): void
    {
        abort_unless($this->canManageLead($user, $lead), 403);
    }

    public function canManageLead(User $user, MarketingLead $lead): bool
    {
        return $this->canManageTeam($user)
            || $lead->assigned_user_id === null
            || (int) $lead->assigned_user_id === (int) $user->id;
    }

    public function canAccessLead(User $user, MarketingLead $lead): bool
    {
        if ($this->canManageLead($user, $lead)) {
            return true;
        }

        return $lead->surveys()->where('assigned_user_id', $user->id)->exists()
            || $lead->followUps()->where('assigned_user_id', $user->id)->exists();
    }

    public function assignedUserId(User $user, mixed $requestedId): ?int
    {
        if (! $this->canManageTeam($user)) {
            return $user->id;
        }

        return $requestedId === null || $requestedId === '' ? null : (int) $requestedId;
    }

    public function validateAssigneeRule(User $user): array
    {
        if (! $this->canManageTeam($user)) {
            return ['nullable'];
        }

        return [
            'nullable',
            'integer',
            Rule::exists('users', 'id')
                ->where(fn ($query) => $query
                    ->whereIn('role', ['staff_marketing', 'tl_marketing'])
                    ->whereIn('division_id', Division::query()
                        ->select('id')
                        ->where('slug', 'marketing'))),
        ];
    }
}
