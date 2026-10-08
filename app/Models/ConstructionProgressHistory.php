<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionProgressHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'construction_progress_id',
        'progress',
        'previous_progress',
        'updated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'decimal:2',
            'previous_progress' => 'decimal:2',
        ];
    }

    public function constructionProgress(): BelongsTo
    {
        return $this->belongsTo(
            ConstructionProgress::class,
            'construction_progress_id'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
