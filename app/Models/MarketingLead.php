<?php

namespace App\Models;

use Database\Factories\MarketingLeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingLead extends Model
{
    /** @use HasFactory<MarketingLeadFactory> */
    use HasFactory;

    public const CATEGORIES = ['Cool', 'Warm', 'Hot', 'Survey', 'Booking', 'Cancel'];

    public const CATEGORY_LABELS = [
        'Cool' => 'Cool (Tidak merespons)',
        'Warm' => 'Warm (Tertarik)',
        'Hot' => 'Hot (Siap booking)',
        'Survey' => 'Survey (Kunjungan)',
        'Booking' => 'Booking',
        'Cancel' => 'Cancel',
    ];

    public const SOURCES = [
        'Tiktok',
        'Rekomendasi',
        'Brosur / Spanduk',
        'Meta Ads',
        'Instagram / FB',
        'Marketplace',
        'Pameran/Sosialisasi',
        'Lokasi Maps',
        'WA BLAST',
    ];

    protected $fillable = [
        'name',
        'date_received',
        'phone',
        'domicile',
        'sales_id',
        'assigned_user_id',
        'archived_at',
        'category',
        'source',
        'last_progress',
        'last_contact_at',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'last_contact_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<Sales, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'lead_id');
    }

    /** @return HasOne<Booking, $this> */
    public function latestBooking(): HasOne
    {
        return $this->hasOne(Booking::class, 'lead_id')->latestOfMany('booking_date');
    }

    /** @return HasMany<MarketingLeadCall, $this> */
    public function calls(): HasMany
    {
        return $this->hasMany(MarketingLeadCall::class, 'lead_id')->latest('called_at');
    }

    /** @return HasMany<MarketingLeadSurvey, $this> */
    public function surveys(): HasMany
    {
        return $this->hasMany(MarketingLeadSurvey::class, 'lead_id')->latest('scheduled_at');
    }

    /** @return HasMany<MarketingLeadFollowUp, $this> */
    public function followUps(): HasMany
    {
        return $this->hasMany(MarketingLeadFollowUp::class, 'lead_id')->latest('due_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
