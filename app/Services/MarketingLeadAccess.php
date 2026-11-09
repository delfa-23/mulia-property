<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Division;
use App\Models\MarketingLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class MarketingLeadAccess
{
    /** @param Builder<Booking> $query
     * @return Builder<Booking>
     */
    public function scopeBookings(Builder $query, User $user): Builder
    {
        if ($this->canManageTeam($user)) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    public function authorizeBooking(User $user, Booking $booking): void
    {
        abort_unless($this->scopeBookings(Booking::query()->whereKey($booking->id), $user)->exists(), 404);
    }

    /** @param Builder<MarketingLead> $query
     * @return Builder<MarketingLead>
     */
    public function scopeLeads(Builder $query, User $user): Builder
    {
        if ($this->canManageTeam($user)) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    public function canManageTeam(User $user): bool
    {
        return $user->isAdmin();
    }

    public function authorizeLead(User $user, MarketingLead $lead): void
    {
        abort_unless($this->canAccessLead($user, $lead), 404);
    }

    public function authorizeLeadOwner(User $user, MarketingLead $lead): void
    {
        abort_unless($this->canManageLead($user, $lead), 404);
    }

    public function canManageLead(User $user, MarketingLead $lead): bool
    {
        return $this->canManageTeam($user)
            || (int) $lead->created_by === (int) $user->id;
    }

    public function canAccessLead(User $user, MarketingLead $lead): bool
    {
        return $this->canManageLead($user, $lead);
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
