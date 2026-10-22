<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusHistory;
use App\Models\JobInterview;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Job openings and the candidate pipeline: received → shortlisted → interview/demo → offered → hired.
 */
class RecruitmentService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private EmployeeService $employeeService,
    ) {}

    /* ---------- Openings ---------- */

    /**
     * @param  array{status?: string|null}  $filters
     */
    public function openingsQuery(array $filters = []): Builder
    {
        return JobOpening::query()
            ->select(['id', 'title', 'department_id', 'designation_id', 'employment_type_id', 'positions', 'opens_on', 'closes_on', 'is_published', 'status', 'salary_min', 'salary_max', 'subjects_note', 'min_qualification_id', 'min_experience_years', 'description', 'requirements', 'created_at'])
            ->with(['department:id,name', 'designation:id,name'])
            ->withCount([
                'applications',
                'applications as hired_count' => fn (Builder $query) => $query->where('status', 'hired'),
            ])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function openingsDatatable(array $filters): array
    {
        return DataTables::eloquent($this->openingsQuery($filters))
            ->escapeColumns([])
            ->addColumn('department_name', fn (JobOpening $opening): ?string => $opening->department?->name)
            ->addColumn('designation_name', fn (JobOpening $opening): ?string => $opening->designation?->name)
            ->editColumn('opens_on', fn (JobOpening $opening): ?string => $opening->opens_on?->toDateString())
            ->editColumn('closes_on', fn (JobOpening $opening): ?string => $opening->closes_on?->toDateString())
            ->removeColumn('department', 'designation')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOpening(array $data): JobOpening
    {
        return DB::transaction(fn (): JobOpening => $this->findOpening(JobOpening::create([
            ...$data,
            'positions' => $data['positions'] ?? 1,
            'is_published' => $data['is_published'] ?? false,
            'status' => $data['status'] ?? 'draft',
        ])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOpening(JobOpening $opening, array $data): JobOpening
    {
        return DB::transaction(function () use ($opening, $data): JobOpening {
            $opening->update($data);

            return $this->findOpening($opening);
        });
    }

    /**
     * @throws ValidationException when candidates have already applied
     */
    public function deleteOpening(JobOpening $opening): void
    {
        if ($opening->applications()->exists()) {
            throw ValidationException::withMessages(['job_opening' => ['Candidates have applied to this opening. Close it instead.']]);
        }

        DB::transaction(fn () => $opening->delete());
    }

    public function findOpening(JobOpening $opening): JobOpening
    {
        return $this->openingsQuery()->whereKey($opening->id)->firstOrFail();
    }

    /* ---------- Applications ---------- */

    /**
     * @param  array{job_opening_id?: int|null, status?: string|null}  $filters
     */
    public function applicationsQuery(array $filters = []): Builder
    {
        return JobApplication::query()
            ->select(['id', 'application_no', 'job_opening_id', 'first_name', 'last_name', 'cnic', 'gender_id', 'date_of_birth', 'phone', 'email', 'address', 'city', 'highest_qualification_id', 'qualification_details', 'experience_years', 'current_employer', 'current_salary', 'expected_salary', 'notice_period_days', 'source_id', 'status', 'rating', 'rejection_reason_id', 'employee_id', 'remarks', 'created_at'])
            ->with('jobOpening:id,title,designation_id,department_id,employment_type_id')
            ->when($filters['job_opening_id'] ?? null, fn (Builder $query, int $id) => $query->where('job_opening_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @param  array{job_opening_id?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function applicationsDatatable(array $filters): array
    {
        return DataTables::eloquent($this->applicationsQuery($filters))
            ->escapeColumns([])
            ->addColumn('full_name', fn (JobApplication $application): string => $application->fullName())
            ->addColumn('opening_title', fn (JobApplication $application): ?string => $application->jobOpening?->title)
            ->addColumn('next_statuses', fn (JobApplication $application): array => JobApplication::TRANSITIONS[$application->status] ?? [])
            ->filterColumn('full_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")))
            ->orderColumn('full_name', 'first_name $1, last_name $1')
            ->removeColumn('job_opening')
            ->toArray();
    }

    public function findApplication(JobApplication $application): JobApplication
    {
        return $application->load(['jobOpening:id,title,designation_id,department_id,employment_type_id', 'interviews.roundType:id,name', 'statusHistories.changedBy:id,name', 'employee:id,employee_code']);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the opening is not taking applications
     */
    public function createApplication(array $data): JobApplication
    {
        $opening = JobOpening::findOrFail($data['job_opening_id']);

        if (! in_array($opening->status, ['open', 'on_hold'], true)) {
            throw ValidationException::withMessages(['job_opening_id' => ['This opening is not taking applications.']]);
        }

        return DB::transaction(function () use ($data): JobApplication {
            $application = JobApplication::create([
                ...$data,
                'application_no' => $this->numberSequenceService->next('job_application_no'),
                'status' => 'received',
            ]);

            $this->history($application, null, 'received', 'Application received');

            return $this->findApplication($application);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateApplication(JobApplication $application, array $data): JobApplication
    {
        return DB::transaction(function () use ($application, $data): JobApplication {
            $application->update(collect($data)->except(['job_opening_id', 'status'])->all());

            return $this->findApplication($application);
        });
    }

    /**
     * Move a candidate along the pipeline; only the steps in JobApplication::TRANSITIONS are allowed.
     *
     * @throws ValidationException
     */
    public function changeStatus(JobApplication $application, string $status, ?string $remarks, ?int $rejectionReasonId = null): JobApplication
    {
        $allowed = JobApplication::TRANSITIONS[$application->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => [
                $allowed === [] ? 'This application is closed ('.$application->status.').' : 'From '.$application->status.' the next step can be: '.implode(', ', $allowed).'.',
            ]]);
        }

        return DB::transaction(function () use ($application, $status, $remarks, $rejectionReasonId): JobApplication {
            $old = $application->status;
            $application->update(['status' => $status, 'rejection_reason_id' => $status === 'rejected' ? $rejectionReasonId : $application->rejection_reason_id]);
            $this->history($application, $old, $status, $remarks);

            return $this->findApplication($application);
        });
    }

    public function deleteApplication(JobApplication $application): void
    {
        if ($application->status === 'hired') {
            throw ValidationException::withMessages(['job_application' => ['A hired candidate\'s application is part of the employee record and cannot be deleted.']]);
        }

        DB::transaction(fn () => $application->delete());
    }

    /* ---------- Interviews ---------- */

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException for closed applications
     */
    public function scheduleInterview(JobApplication $application, array $data): JobInterview
    {
        if (in_array($application->status, ['hired', 'rejected', 'withdrawn'], true)) {
            throw ValidationException::withMessages(['job_application' => ['This application is closed.']]);
        }

        return DB::transaction(function () use ($application, $data): JobInterview {
            $interview = JobInterview::create([
                ...$data,
                'job_application_id' => $application->id,
                'round_no' => (int) $application->interviews()->max('round_no') + 1,
                'mode' => $data['mode'] ?? 'in_person',
                'status' => 'scheduled',
            ]);

            if (in_array($application->status, ['received', 'shortlisted', 'on_hold'], true)) {
                $old = $application->status;
                $application->update(['status' => 'interview']);
                $this->history($application, $old, 'interview', 'Interview round '.$interview->round_no.' scheduled');
            }

            return $interview->load('roundType:id,name');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateInterview(JobInterview $interview, array $data): JobInterview
    {
        return DB::transaction(function () use ($interview, $data): JobInterview {
            $interview->update($data);

            return $interview->load('roundType:id,name');
        });
    }

    /* ---------- Hiring ---------- */

    /**
     * Turn an offered candidate into an employee; the opening is marked filled when all positions are taken.
     *
     * @param  array<string, mixed>  $data  employee fields to complete (designation, joining date, portal login…)
     *
     * @throws ValidationException
     */
    public function hire(JobApplication $application, array $data): Employee
    {
        if ($application->status !== 'offered') {
            throw ValidationException::withMessages(['status' => ['Only a candidate with an accepted offer can be hired. Move the application to "offered" first.']]);
        }

        return DB::transaction(function () use ($application, $data): Employee {
            $opening = $application->jobOpening;

            $employee = $this->employeeService->create([
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'cnic' => $application->cnic,
                'gender_id' => $data['gender_id'] ?? $application->gender_id,
                'date_of_birth' => $application->date_of_birth?->toDateString(),
                'phone' => $application->phone,
                'email' => $application->email,
                'current_address' => $application->address,
                'city' => $application->city,
                'job_application_id' => $application->id,
                ...$data,
                'designation_id' => $data['designation_id'] ?? $opening->designation_id,
                'department_id' => $data['department_id'] ?? $opening->department_id,
                'employment_type_id' => $data['employment_type_id'] ?? $opening->employment_type_id,
            ]);

            $application->update(['status' => 'hired', 'employee_id' => $employee->id]);
            $this->history($application, 'offered', 'hired', 'Hired as '.$employee->employee_code);

            if ($opening->applications()->where('status', 'hired')->count() >= $opening->positions) {
                $opening->update(['status' => 'filled']);
            }

            return $employee;
        });
    }

    private function history(JobApplication $application, ?string $from, string $to, ?string $remarks): void
    {
        JobApplicationStatusHistory::create([
            'job_application_id' => $application->id,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'changed_by' => auth()->id(),
        ]);
    }
}
