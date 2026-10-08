<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'birth_place',
        'birth_date',
        'nik',
        'marital_status',
        'occupation',
        'phone',
        'email',
        'address',
        'ktp_file',
        'kk_file',
        'npwp_file',
        'booking_form_file',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function pemberkasanDocuments(): HasMany
    {
        return $this->hasMany(PemberkasanDocument::class);
    }
}
