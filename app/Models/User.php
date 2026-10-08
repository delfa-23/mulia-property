<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property int|null $division_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Division|null $division
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'email', 'password', 'current_team_id', 'role', 'division_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable, PasskeyAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /** @return BelongsTo<Division, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function documentProcesses()
    {
        return $this->hasMany(DocumentProcess::class, 'pic_id');
    }

    public function uploadedPemberkasanDocuments()
    {
        return $this->hasMany(PemberkasanDocument::class, 'uploaded_by');
    }

    public function bankProcesses()
    {
        return $this->hasMany(BankProcess::class, 'pic_id');
    }

    public function createdSp3()
    {
        return $this->hasMany(Sp3::class, 'created_by');
    }

    public function createdAkadSchedules()
    {
        return $this->hasMany(AkadSchedule::class, 'created_by');
    }

    public function constructionProgresses()
    {
        return $this->hasMany(ConstructionProgress::class, 'updated_by');
    }

    public function progressReports()
    {
        return $this->hasMany(ProgressReport::class, 'created_by');
    }

    public function reportReviews()
    {
        return $this->hasMany(ReportReview::class, 'reviewed_by');
    }

    public function weeklyReports()
    {
        return $this->hasMany(WeeklyReport::class, 'created_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTl(): bool
    {
        return str_starts_with($this->role, 'tl_');
    }

    public function isStaff(): bool
    {
        return str_starts_with($this->role, 'staff_');
    }

    public static function divisionSlugForRole(string $role): ?string
    {
        return match ($role) {
            'tl_pembangunan', 'staff_pembangunan' => 'pembangunan',
            'tl_marketing', 'staff_marketing' => 'marketing',
            'tl_pemberkasan', 'staff_pemberkasan' => 'pemberkasan',
            default => null,
        };
    }

    public function isAssignedToDivision(string $divisionSlug): bool
    {
        return self::divisionSlugForRole($this->role) === $divisionSlug
            && $this->division?->slug === $divisionSlug;
    }

    public function hasValidDivisionAssignment(): bool
    {
        $divisionSlug = self::divisionSlugForRole($this->role);

        return $divisionSlug !== null && $this->division?->slug === $divisionSlug;
    }

    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'tl_pembangunan', 'staff_pembangunan' => 'pembangunan.dashboard',
            'tl_marketing' => 'marketing.dashboard',
            'staff_marketing' => 'marketing.dashboard',
            'tl_pemberkasan', 'staff_pemberkasan' => 'pemberkasan.dashboard',
            default => 'dashboard',
        };
    }
}
