<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id',
        'lot_number',
        'house_price',
        'status',
        'notes',
        'image_path',
        'image_paths',
        'map_coordinates',
    ];

    protected function casts(): array
    {
        return [
            'house_price' => 'decimal:2',
            'image_paths' => 'array',
            'map_coordinates' => 'array',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->imageGallery()[0]['url'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function imagePaths(): array
    {
        $paths = array_merge(
            is_string($this->image_path) && $this->image_path !== '' ? [$this->image_path] : [],
            is_array($this->image_paths) ? $this->image_paths : [],
        );

        return array_values(array_unique(array_filter(
            $paths,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        )));
    }

    /**
     * @return list<array{path: string, url: string}>
     */
    public function imageGallery(): array
    {
        return array_map(function (string $path): array {
            $disk = Storage::disk('site-plans');
            if ($disk->exists($path)) {
                return ['path' => $path, 'url' => $disk->url($path)];
            }

            $publicDisk = Storage::disk('public');
            if ($publicDisk->exists($path)) {
                return ['path' => $path, 'url' => $publicDisk->url($path)];
            }

            return ['path' => $path, 'url' => $disk->url($path)];
        }, $this->imagePaths());
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function sitePlanAreas(): HasMany
    {
        return $this->hasMany(SitePlanArea::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function constructionProgresses(): HasMany
    {
        return $this->hasMany(ConstructionProgress::class);
    }

    public function constructionProgressPercentage(): float
    {
        return (float) $this->constructionProgresses
            ->whereNotNull('stage_id')
            ->sum(fn (ConstructionProgress $progress): float => $progress->weightedProgressPercentage());
    }

    public function pemberkasanDocuments(): HasMany
    {
        return $this->hasMany(PemberkasanDocument::class);
    }

    public function constructionProgressHistories(): HasManyThrough
    {
        return $this->hasManyThrough(
            ConstructionProgressHistory::class,
            ConstructionProgress::class,
            'lot_id',
            'construction_progress_id'
        );
    }

    public function incomeTransactions(): HasMany
    {
        return $this->hasMany(IncomeTransaction::class);
    }

    public function expenseTransactions(): HasMany
    {
        return $this->hasMany(ExpenseTransaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
