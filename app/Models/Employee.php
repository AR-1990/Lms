<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'campus_id', 'user_id', 'employee_code', 'job_application_id', 'first_name', 'middle_name', 'last_name', 'father_or_spouse_name',
    'gender_id', 'date_of_birth', 'cnic', 'cnic_expiry', 'marital_status_id', 'religion_id', 'blood_group_id', 'nationality_id', 'photo_path',
    'phone', 'alt_phone', 'email', 'personal_email', 'current_address', 'permanent_address', 'city', 'department_id', 'designation_id',
    'employment_type_id', 'reports_to_id', 'joining_date', 'probation_end_date', 'confirmation_date', 'contract_end_date', 'is_teaching_staff',
    'ntn', 'eobi_no', 'bank_id', 'bank_account_title', 'bank_account_no', 'iban', 'status', 'exit_date',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    /**
     * Statuses that still count as working at the school.
     *
     * @var array<int, string>
     */
    public const CURRENT_STATUSES = ['active', 'on_leave', 'suspended'];

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['active', 'on_leave', 'suspended', 'resigned', 'terminated', 'retired'];

    protected string $activityModule = 'employees';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'cnic_expiry' => 'date',
            'joining_date' => 'date',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'contract_end_date' => 'date',
            'exit_date' => 'date',
            'is_teaching_staff' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function activityLabel(): string
    {
        return $this->fullName().' ('.$this->employee_code.')';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'employment_type_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'gender_id');
    }

    public function bloodGroup(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'blood_group_id');
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EmployeeStatusHistory::class)->latest('effective_on')->latest('id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
