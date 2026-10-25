<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'application_no', 'job_opening_id', 'first_name', 'last_name', 'cnic', 'gender_id', 'date_of_birth', 'phone', 'email', 'address',
    'city', 'highest_qualification_id', 'qualification_details', 'experience_years', 'current_employer', 'current_salary', 'expected_salary',
    'notice_period_days', 'source_id', 'referred_by_employee_id', 'photo_path', 'status', 'rating', 'rejection_reason_id', 'employee_id', 'remarks',
])]
class JobApplication extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Allowed next steps for each status. `hired` is only reached through the hire action.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'received' => ['shortlisted', 'on_hold', 'rejected', 'withdrawn'],
        'shortlisted' => ['interview', 'demo', 'on_hold', 'rejected', 'withdrawn'],
        'interview' => ['demo', 'offered', 'on_hold', 'rejected', 'withdrawn'],
        'demo' => ['offered', 'on_hold', 'rejected', 'withdrawn'],
        'offered' => ['rejected', 'withdrawn'],
        'on_hold' => ['shortlisted', 'interview', 'rejected', 'withdrawn'],
        'hired' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    protected string $activityModule = 'job_applications';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'experience_years' => 'decimal:1',
            'current_salary' => 'integer',
            'expected_salary' => 'integer',
            'rating' => 'integer',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function activityLabel(): string
    {
        return $this->fullName().' ('.$this->application_no.')';
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(JobApplicationStatusHistory::class)->latest('id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(JobInterview::class)->orderBy('round_no');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
