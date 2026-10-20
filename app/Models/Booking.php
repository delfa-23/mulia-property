<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    public const DP_PERCENTAGE = 100.0;

    protected $fillable = [
        'lot_id',
        'customer_id',
        'sales_id',
        'lead_id',
        'booking_date',
        'blocking_until',
        'house_price_snapshot',
        'dp_percentage',
        'akad_fee',
        'status',
        'notes',
        'applicant_work_location_url',
        'spouse_work_location_url',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'blocking_until' => 'datetime',
            'house_price_snapshot' => 'decimal:2',
            'dp_percentage' => 'decimal:2',
            'akad_fee' => 'decimal:2',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class);
    }

    /** @return BelongsTo<MarketingLead, $this> */
    public function marketingLead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'lead_id');
    }

    public function documentProcess(): HasOne
    {
        return $this->hasOne(DocumentProcess::class);
    }

    public function pemberkasanDocuments(): HasMany
    {
        return $this->hasMany(PemberkasanDocument::class);
    }

    public function bankProcess(): HasOne
    {
        return $this->hasOne(BankProcess::class);
    }

    public function sp3(): HasOne
    {
        return $this->hasOne(Sp3::class);
    }

    public function akadSchedule(): HasOne
    {
        return $this->hasOne(AkadSchedule::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(BookingHistory::class)->latest('changed_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->latest('paid_at');
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(BookingPaymentOrder::class)->latest();
    }
}
