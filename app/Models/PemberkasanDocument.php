<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemberkasanDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'property_id',
        'lot_id',
        'customer_id',
        'document_type',
        'original_name',
        'mime_type',
        'file_size',
        'local_path',
        'replacement_local_path',
        'drive_file_id',
        'drive_folder_id',
        'drive_url',
        'status',
        'upload_error',
        'retry_count',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'retry_count' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
