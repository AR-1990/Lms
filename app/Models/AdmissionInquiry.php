<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'inquiry_no', 'inquiry_date', 'source_id', 'parent_name', 'phone', 'email', 'child_name', 'child_dob', 'class_sought_id',
    'academic_session_id', 'previous_school', 'message', 'status', 'follow_up_on', 'assigned_to', 'closed_reason_id', 'application_id',
])]
class AdmissionInquiry extends Model
{
    use BelongsToCampus, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * `converted` is set only when an application is created from the inquiry.
     *
     * @var array<int, string>
     */
    public const STATUSES = ['new', 'contacted', 'visit_scheduled', 'converted', 'closed'];

    protected string $activityModule = 'admission_inquiries';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['inquiry_date' => 'date', 'child_dob' => 'date', 'follow_up_on' => 'date'];
    }

    public function activityLabel(): string
    {
        return $this->inquiry_no.' — '.$this->parent_name;
    }

    public function classSought(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_sought_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'source_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'application_id');
    }
}
