<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'title', 'department_id', 'designation_id', 'employment_type_id', 'positions', 'subjects_note', 'min_qualification_id',
    'min_experience_years', 'salary_min', 'salary_max', 'description', 'requirements', 'opens_on', 'closes_on', 'is_published', 'status',
])]
class JobOpening extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['draft', 'open', 'on_hold', 'closed', 'filled'];

    protected string $activityModule = 'job_openings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_published' => 'boolean',
            'positions' => 'integer',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'min_experience_years' => 'decimal:1',
        ];
    }

    public function activityLabel(): string
    {
        return $this->title;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
