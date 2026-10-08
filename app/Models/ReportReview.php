<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_report_id',
        'reviewed_by',
        'action',
        'notes',
    ];

    public function progressReport(): BelongsTo
    {
        return $this->belongsTo(
            ProgressReport::class
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }
}
