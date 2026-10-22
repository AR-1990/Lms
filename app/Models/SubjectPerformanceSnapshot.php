<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['performance_snapshot_id', 'subject_id', 'average_percent', 'highest_percent', 'class_average_percent', 'change_from_previous'])]
class SubjectPerformanceSnapshot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['average_percent' => 'float', 'highest_percent' => 'float', 'class_average_percent' => 'float', 'change_from_previous' => 'float'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
