<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'campus_id', 'user_id', 'admission_no', 'admission_date', 'application_id', 'first_name', 'middle_name', 'last_name', 'gender_id',
    'date_of_birth', 'place_of_birth', 'b_form_no', 'nationality_id', 'religion_id', 'blood_group_id', 'mother_tongue', 'photo_path', 'phone',
    'email', 'address_line', 'city', 'postal_code', 'permanent_address', 'house_id', 'previous_school', 'previous_class', 'current_status',
    'left_on', 'leaving_reason_id', 'slc_no', 'slc_issued_on', 'slc_conduct_id', 'slc_remarks', 'is_staff_child', 'staff_parent_id', 'special_needs', 'remarks',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToCampus, HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['active', 'suspended', 'withdrawn', 'graduated', 'left'];

    /**
     * Statuses that end the student's time at school.
     *
     * @var array<int, string>
     */
    public const LEAVING_STATUSES = ['withdrawn', 'graduated', 'left'];

    protected string $activityModule = 'students';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'date_of_birth' => 'date',
            'left_on' => 'date',
            'slc_issued_on' => 'date',
            'is_staff_child' => 'boolean',
        ];
    }

    /**
     * The student linked to this login plus the children of a parent login.
     *
     * @return array<int, int>
     */
    public static function ownIdsFor(User $user): array
    {
        return static::where('user_id', $user->id)->pluck('id')
            ->merge(DB::table('guardian_student')->whereNull('deleted_at')->whereIn('guardian_id', Guardian::where('user_id', $user->id)->select('id'))->pluck('student_id'))
            ->unique()
            ->values()
            ->all();
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function activityLabel(): string
    {
        return $this->fullName().' ('.$this->admission_no.')';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'gender_id');
    }

    public function leavingReason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'leaving_reason_id');
    }

    public function slcConduct(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'slc_conduct_id');
    }

    public function bloodGroup(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'blood_group_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['relation_id', 'is_primary_contact', 'is_fee_payer', 'can_pick_up', 'lives_with_student'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Enrolment in the current academic session.
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->whereIn('academic_session_id', AcademicSession::where('is_current', true)->select('id'));
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)->latest('effective_on')->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function concessions(): HasMany
    {
        return $this->hasMany(StudentConcession::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
