<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'application_no', 'academic_session_id', 'class_applied_id', 'applied_on', 'first_name', 'middle_name', 'last_name', 'gender_id',
    'date_of_birth', 'place_of_birth', 'b_form_no', 'nationality_id', 'religion_id', 'blood_group_id', 'photo_path', 'address_line', 'city',
    'postal_code', 'previous_school', 'previous_class', 'previous_result', 'reason_for_leaving', 'has_sibling_in_school', 'sibling_student_id',
    'special_needs', 'medical_notes', 'transport_required', 'inquiry_id', 'status', 'decision_on', 'decision_by', 'rejection_reason_id',
    'offer_expires_on', 'student_id', 'remarks',
])]
class AdmissionApplication extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Allowed manual next steps. `accepted` creates the admission invoice; `enrolled` is reached
     * only through the enrol action; test/interview statuses also move when tests or interviews are recorded.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'submitted' => ['documents_pending', 'documents_verified', 'test_scheduled', 'interview_scheduled', 'offered', 'waitlisted', 'rejected', 'withdrawn'],
        'documents_pending' => ['documents_verified', 'rejected', 'withdrawn'],
        'documents_verified' => ['test_scheduled', 'interview_scheduled', 'offered', 'waitlisted', 'rejected', 'withdrawn'],
        'test_scheduled' => ['test_done', 'rejected', 'withdrawn'],
        'test_done' => ['interview_scheduled', 'offered', 'waitlisted', 'rejected', 'withdrawn'],
        'interview_scheduled' => ['interviewed', 'rejected', 'withdrawn'],
        'interviewed' => ['offered', 'waitlisted', 'rejected', 'withdrawn'],
        'waitlisted' => ['offered', 'rejected', 'withdrawn'],
        'offered' => ['accepted', 'rejected', 'withdrawn'],
        'accepted' => ['withdrawn'],
        'enrolled' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    protected string $activityModule = 'admission_applications';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_on' => 'date',
            'date_of_birth' => 'date',
            'decision_on' => 'date',
            'offer_expires_on' => 'date',
            'has_sibling_in_school' => 'boolean',
            'transport_required' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function activityLabel(): string
    {
        return $this->fullName().' ('.$this->application_no.')';
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function classApplied(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_applied_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'gender_id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(AdmissionApplicationGuardian::class, 'application_id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(AdmissionTest::class, 'application_id')->orderBy('scheduled_at');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(AdmissionInterview::class, 'application_id')->orderBy('scheduled_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AdmissionStatusHistory::class, 'application_id')->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
