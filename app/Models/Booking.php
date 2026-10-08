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

    protected $fillable = [
        'lot_id',
        'customer_id',
        'sales_id',
        'booking_date',
        'blocking_until',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'blocking_until' => 'datetime',
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
}
