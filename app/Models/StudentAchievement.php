<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's achievement (academic, sports, arts…); the certificate scan is an attachment.
 */
#[Fillable(['student_id', 'title', 'category_id', 'level', 'achieved_on', 'description'])]
class StudentAchievement extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'students';

    /**
     * @var array<int, string>
     */
    public const LEVELS = ['school', 'district', 'provincial', 'national', 'international'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['achieved_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }
}
