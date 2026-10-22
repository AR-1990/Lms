<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parent's "seen" on a diary page — the digital signature.
 */
#[Fillable(['diary_id', 'student_id', 'guardian_id', 'acknowledged_at', 'parent_comment', 'device'])]
class DiaryAcknowledgement extends Model
{
    use LogsActivity;

    protected string $activityModule = 'diary';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'diary signed for student #'.$this->student_id;
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
