<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressReport extends Model
{
    use HasFactory;

    public const PUBLIC_PHOTO_DISK = 'report-photos';

    public const PUBLIC_PHOTO_DIRECTORY = 'images/progress-reports';

    protected $fillable = [
        'division_id',
        'property_id',
        'lot_id',
        'created_by',
        'report_date',
        'period_start',
        'period_end',
        'title',
        'content',
        'photo_path',
        'photo_archived_at',
        'progress',
        'status',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'report_date' => 'date',
            'progress' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'photo_archived_at' => 'datetime',
        ];
    }

    public static function isPublicPhotoPath(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PUBLIC_PHOTO_DIRECTORY.'/');
    }

    public function photoUrl(): ?string
    {
        if (! is_string($this->photo_path) || $this->photo_path === '') {
            return null;
        }

        return self::isPublicPhotoPath($this->photo_path)
            ? asset($this->photo_path)
            : route('reports.progress.photo', $this);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class);
    }
}
