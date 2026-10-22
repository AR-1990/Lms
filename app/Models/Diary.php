<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One diary page per section per school day; parents see it once published.
 */
#[Fillable(['section_id', 'diary_date', 'class_teacher_note', 'status', 'published_at', 'published_by'])]
class Diary extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    public const STATUSES = ['draft', 'published'];

    protected string $activityModule = 'diary';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['diary_date' => 'date', 'published_at' => 'datetime'];
    }

    public function activityLabel(): string
    {
        return 'diary '.$this->diary_date?->toDateString();
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(DiaryStudentRemark::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(DiaryAcknowledgement::class);
    }
}
