<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\AppraisalCriterion;
use App\Models\AppraisalCycle;
use App\Models\AppraisalScore;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Staff appraisals (PLAN.md §14.5). HR launches a cycle and every current employee gets an appraisal with
 * their manager (or department head) as reviewer. Flow: self_review (optional) → draft (reviewer scoring)
 * → submitted → acknowledged by the employee. Scores are 1–5 per criterion; the overall is the weighted mean.
 */
class AppraisalService
{
    public const MAX_SCORE = 5;

    public function __construct(private NotificationService $notificationService, private ActivityLogger $activityLogger) {}

    /* ---------- Criteria ---------- */

    /**
     * @return array<string, mixed>
     */
    public function criteriaDatatable(): array
    {
        return DataTables::eloquent(AppraisalCriterion::query()->select(['id', 'name', 'description', 'weight_percent', 'applies_to', 'sort_order', 'is_active', 'created_at']))->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCriterion(?AppraisalCriterion $criterion, array $data): AppraisalCriterion
    {
        $criterion ??= new AppraisalCriterion(['is_active' => true]);
        $criterion->fill([...$data, 'weight_percent' => $data['weight_percent'] ?? 0, 'sort_order' => $data['sort_order'] ?? 0])->save();

        return $criterion;
    }

    public function deleteCriterion(AppraisalCriterion $criterion): void
    {
        $inUse = AppraisalScore::where('appraisal_criterion_id', $criterion->id)
            ->whereHas('appraisal.cycle', fn (Builder $query) => $query->where('status', 'open'))->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['criterion' => ['An open cycle uses this criterion. Close the cycle or mark the criterion inactive.']]);
        }

        $criterion->delete();
    }

    /* ---------- Cycles ---------- */

    /**
     * @return array<string, mixed>
     */
    public function cyclesDatatable(): array
    {
        $query = AppraisalCycle::query()
            ->select(['id', 'name', 'starts_on', 'ends_on', 'department_id', 'self_review', 'status', 'launched_at', 'created_at'])
            ->with('department:id,name')
            ->withCount(['appraisals', 'appraisals as done_count' => fn (Builder $query) => $query->whereIn('status', ['submitted', 'acknowledged'])])
            ->withAvg('appraisals as average_score', 'overall_score');

        return DataTables::eloquent($query)
            ->addColumn('department_name', fn (AppraisalCycle $cycle): string => $cycle->department?->name ?? 'All staff')
            ->editColumn('starts_on', fn (AppraisalCycle $cycle): ?string => $cycle->starts_on?->toDateString())
            ->editColumn('ends_on', fn (AppraisalCycle $cycle): ?string => $cycle->ends_on?->toDateString())
            ->editColumn('average_score', fn (AppraisalCycle $cycle): ?float => $cycle->average_score === null ? null : round((float) $cycle->average_score, 2))
            ->removeColumn('department')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCycle(?AppraisalCycle $cycle, array $data): AppraisalCycle
    {
        if ($cycle !== null && $cycle->status === 'closed') {
            throw ValidationException::withMessages(['cycle' => ['A closed cycle cannot change.']]);
        }

        $cycle ??= new AppraisalCycle(['status' => 'draft']);
        $cycle->fill($cycle->status === 'draft' ? $data : collect($data)->only(['name', 'ends_on'])->all())->save();

        return $cycle;
    }

    public function deleteCycle(AppraisalCycle $cycle): void
    {
        if ($cycle->status !== 'draft') {
            throw ValidationException::withMessages(['cycle' => ['Only a draft cycle can be deleted; close it instead.']]);
        }

        $cycle->delete();
    }

    /**
     * Open the cycle: one appraisal per current employee (of the department, if set) with the criteria that
     * apply to them and an automatic reviewer. Running it again adds staff who joined since.
     */
    public function launch(AppraisalCycle $cycle, User $user): int
    {
        if ($cycle->status === 'closed') {
            throw ValidationException::withMessages(['cycle' => ['This cycle is closed.']]);
        }

        $criteria = AppraisalCriterion::where('is_active', true)->orderBy('sort_order')->get(['id', 'applies_to']);

        if ($criteria->isEmpty()) {
            throw ValidationException::withMessages(['cycle' => ['Add at least one active criterion first.']]);
        }

        $heads = Department::pluck('head_employee_id', 'id');
        $already = Appraisal::where('appraisal_cycle_id', $cycle->id)->pluck('employee_id')->all();
        $staff = Employee::whereIn('status', Employee::CURRENT_STATUSES)
            ->when($cycle->department_id, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->whereNotIn('id', $already)
            ->get(['id', 'user_id', 'department_id', 'reports_to_id', 'is_teaching_staff']);

        $created = DB::transaction(function () use ($cycle, $criteria, $heads, $staff): array {
            $notify = [];

            foreach ($staff as $employee) {
                $reviewer = $employee->reports_to_id ?? $heads[$employee->department_id] ?? null;
                $appraisal = Appraisal::create([
                    'appraisal_cycle_id' => $cycle->id, 'employee_id' => $employee->id,
                    'reviewer_id' => $reviewer === $employee->id ? null : $reviewer,
                    'status' => $cycle->self_review ? 'self_review' : 'draft',
                ]);

                foreach ($criteria->filter(fn (AppraisalCriterion $criterion): bool => $criterion->applies_to === 'all' || ($criterion->applies_to === 'teaching') === (bool) $employee->is_teaching_staff) as $criterion) {
                    $appraisal->scores()->create(['appraisal_criterion_id' => $criterion->id]);
                }

                $notify[] = [$employee->user_id, $appraisal->reviewer_id];
            }

            $cycle->update(['status' => 'open', 'launched_at' => $cycle->launched_at ?? now()]);

            return $notify;
        });

        $url = route('my-hr');
        $this->notificationService->notify(array_column($created, 0), 'appraisal_started', "{$cycle->name} has started", $cycle->self_review ? 'Rate yourself in My HR before your reviewer scores you.' : 'Your reviewer will score you; you will see it in My HR.', $url, $cycle);
        $reviewerUsers = Employee::whereIn('id', array_filter(array_column($created, 1)))->pluck('user_id')->filter()->all();
        $this->notificationService->notify($reviewerUsers, 'appraisal_to_review', "{$cycle->name}: you have staff to review", 'Open My HR → Reviews to do.', $url, $cycle);
        $this->activityLogger->record('appraisals', 'launched', "{$user->name} launched {$cycle->name} for ".count($created).' staff', $cycle);

        return count($created);
    }

    public function close(AppraisalCycle $cycle, User $user): AppraisalCycle
    {
        $cycle->update(['status' => 'closed', 'closed_at' => now()]);
        $this->activityLogger->record('appraisals', 'closed', "{$user->name} closed {$cycle->name}", $cycle);

        return $cycle;
    }

    /**
     * Progress and results of a cycle for HR.
     *
     * @return array<string, mixed>
     */
    public function summary(AppraisalCycle $cycle): array
    {
        $appraisals = Appraisal::where('appraisal_cycle_id', $cycle->id)->with(['employee:id,department_id', 'employee.department:id,name', 'rating:id,name'])->get(['id', 'employee_id', 'status', 'overall_score', 'rating_id', 'reviewer_id']);

        return [
            ...$cycle->attributesToArray(),
            'starts_on' => $cycle->starts_on?->toDateString(),
            'ends_on' => $cycle->ends_on?->toDateString(),
            'total' => $appraisals->count(),
            'by_status' => collect(Appraisal::STATUSES)->mapWithKeys(fn (string $status): array => [$status => $appraisals->where('status', $status)->count()])->all(),
            'without_reviewer' => $appraisals->whereNull('reviewer_id')->count(),
            'average_score' => $appraisals->whereNotNull('overall_score')->avg('overall_score') === null ? null : round((float) $appraisals->whereNotNull('overall_score')->avg('overall_score'), 2),
            'by_department' => $appraisals->whereNotNull('overall_score')->groupBy(fn (Appraisal $appraisal): string => $appraisal->employee?->department?->name ?? 'No department')
                ->map(fn ($group, string $name): array => ['department' => $name, 'count' => $group->count(), 'average' => round((float) $group->avg('overall_score'), 2)])->values()->all(),
            'by_rating' => $appraisals->whereNotNull('rating_id')->groupBy(fn (Appraisal $appraisal): string => $appraisal->rating?->name ?? '—')
                ->map(fn ($group, string $name): array => ['rating' => $name, 'count' => $group->count()])->values()->all(),
        ];
    }

    /* ---------- Appraisals ---------- */

    /**
     * @param  array{appraisal_cycle_id?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function appraisalsDatatable(array $filters): array
    {
        $query = Appraisal::query()
            ->select(['id', 'appraisal_cycle_id', 'employee_id', 'reviewer_id', 'status', 'overall_score', 'self_score', 'rating_id', 'submitted_at', 'created_at'])
            ->with(['employee:id,first_name,middle_name,last_name,employee_code,department_id', 'employee.department:id,name', 'reviewer:id,first_name,middle_name,last_name', 'rating:id,name', 'cycle:id,name'])
            ->when($filters['appraisal_cycle_id'] ?? null, fn (Builder $query, int $id) => $query->where('appraisal_cycle_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        return DataTables::eloquent($query)
            ->addColumn('employee_name', fn (Appraisal $row): ?string => $row->employee?->fullName())
            ->addColumn('employee_code', fn (Appraisal $row): ?string => $row->employee?->employee_code)
            ->addColumn('department_name', fn (Appraisal $row): ?string => $row->employee?->department?->name)
            ->addColumn('reviewer_name', fn (Appraisal $row): ?string => $row->reviewer?->fullName())
            ->addColumn('rating_name', fn (Appraisal $row): ?string => $row->rating?->name)
            ->addColumn('cycle_name', fn (Appraisal $row): ?string => $row->cycle?->name)
            ->filterColumn('employee_name', fn (Builder $query, string $keyword) => $query->whereHas('employee', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%")->orWhere('employee_code', 'like', "%{$keyword}%"))))
            ->filterColumn('reviewer_name', fn (Builder $query, string $keyword) => $query->whereHas('reviewer', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%"))))
            ->filterColumn('department_name', fn (Builder $query, string $keyword) => $query->whereHas('employee.department', fn (Builder $inner) => $inner->where('name', 'like', "%{$keyword}%")))
            ->removeColumn('employee', 'reviewer', 'rating', 'cycle')
            ->toArray();
    }

    /**
     * One appraisal with its scores and what the viewer may do with it.
     *
     * @return array<string, mixed>
     */
    public function details(Appraisal $appraisal, User $viewer): array
    {
        $appraisal->load(['cycle:id,name,status,self_review', 'employee:id,user_id,first_name,middle_name,last_name,employee_code,department_id,designation_id', 'employee.department:id,name', 'employee.designation:id,name', 'reviewer:id,user_id,first_name,middle_name,last_name', 'rating:id,name', 'scores.criterion:id,name,description,weight_percent']);
        $role = $this->roleOf($appraisal, $viewer);
        $open = $appraisal->cycle?->status === 'open';
        $showReview = $role !== 'employee' || in_array($appraisal->status, ['submitted', 'acknowledged'], true);

        return [
            'id' => $appraisal->id,
            'cycle' => $appraisal->cycle?->name,
            'cycle_open' => $open,
            'status' => $appraisal->status,
            'employee' => ['name' => $appraisal->employee?->fullName(), 'code' => $appraisal->employee?->employee_code, 'department' => $appraisal->employee?->department?->name, 'designation' => $appraisal->employee?->designation?->name],
            'reviewer' => $appraisal->reviewer?->fullName(),
            'reviewer_id' => $appraisal->reviewer_id,
            'overall_score' => $showReview ? $appraisal->overall_score : null,
            'self_score' => $appraisal->self_score,
            'rating_id' => $showReview ? $appraisal->rating_id : null,
            'rating' => $showReview ? $appraisal->rating?->name : null,
            'strengths' => $showReview ? $appraisal->strengths : null,
            'improvements' => $showReview ? $appraisal->improvements : null,
            'goals' => $showReview ? $appraisal->goals : null,
            'employee_comments' => $appraisal->employee_comments,
            'submitted_at' => $appraisal->submitted_at?->toDateString(),
            'acknowledged_at' => $appraisal->acknowledged_at?->toDateString(),
            'scores' => $appraisal->scores->sortBy(fn (AppraisalScore $score): int => (int) $score->criterion?->sort_order)->values()->map(fn (AppraisalScore $score): array => [
                'id' => $score->id, 'criterion' => $score->criterion?->name, 'description' => $score->criterion?->description, 'weight' => $score->criterion?->weight_percent,
                'score' => $showReview ? $score->score : null, 'comment' => $showReview ? $score->comment : null,
                'self_score' => $score->self_score, 'self_comment' => $score->self_comment,
            ])->all(),
            'viewer_role' => $role,
            'can' => [
                'self_review' => $open && $role === 'employee' && $appraisal->status === 'self_review',
                'review' => $open && in_array($role, ['reviewer', 'hr'], true) && in_array($appraisal->status, ['self_review', 'draft'], true),
                'acknowledge' => $role === 'employee' && $appraisal->status === 'submitted',
                'reopen' => $open && $role === 'hr' && $appraisal->status === 'submitted',
                'reassign' => $open && $role === 'hr' && $appraisal->status !== 'acknowledged',
            ],
            'ratings' => Lookup::query()->ofType('appraisal_rating')->active()->orderBy('sort_order')->get(['id', 'name'])->all(),
        ];
    }

    /**
     * The employee rates themselves; submitting hands the appraisal to the reviewer.
     *
     * @param  array<int, array{id: int, self_score?: float|null, self_comment?: string|null}>  $scores
     */
    public function saveSelfReview(Appraisal $appraisal, array $scores, bool $submit, User $user): Appraisal
    {
        if ($this->roleOf($appraisal, $user) !== 'employee' || $appraisal->status !== 'self_review' || $appraisal->cycle?->status !== 'open') {
            throw ValidationException::withMessages(['appraisal' => ['Self-review is not open for you on this appraisal.']]);
        }

        return DB::transaction(function () use ($appraisal, $scores, $submit, $user): Appraisal {
            $this->writeScores($appraisal, $scores, 'self_score', 'self_comment');

            if ($submit) {
                $this->ensureComplete($appraisal, 'self_score');
                $appraisal->update(['status' => 'draft', 'self_score' => $this->weighted($appraisal, 'self_score'), 'self_reviewed_at' => now()]);
                $reviewerUser = Employee::whereKey($appraisal->reviewer_id)->value('user_id');
                $this->notificationService->notify(array_filter([$reviewerUser]), 'appraisal_to_review', $appraisal->employee?->fullName().' finished their self-review', 'You can now score them in My HR → Reviews to do.', route('my-hr'), $appraisal);
                $this->activityLogger->record('appraisals', 'self_reviewed', "{$user->name} submitted their self-review", $appraisal);
            }

            return $appraisal->refresh();
        });
    }

    /**
     * The reviewer (or HR) scores each criterion and writes the summary; submitting shows it to the employee.
     *
     * @param  array{scores?: array<int, array{id: int, score?: float|null, comment?: string|null}>, rating_id?: int|null, strengths?: string|null, improvements?: string|null, goals?: string|null}  $data
     */
    public function saveReview(Appraisal $appraisal, array $data, bool $submit, User $user): Appraisal
    {
        if (! in_array($this->roleOf($appraisal, $user), ['reviewer', 'hr'], true) || ! in_array($appraisal->status, ['self_review', 'draft'], true) || $appraisal->cycle?->status !== 'open') {
            throw ValidationException::withMessages(['appraisal' => ['This appraisal cannot be scored by you now.']]);
        }

        return DB::transaction(function () use ($appraisal, $data, $submit, $user): Appraisal {
            $this->writeScores($appraisal, $data['scores'] ?? [], 'score', 'comment');
            $overall = $this->weighted($appraisal, 'score');
            $appraisal->fill([...collect($data)->only(['rating_id', 'strengths', 'improvements', 'goals'])->all(), 'overall_score' => $overall]);

            if ($submit) {
                $this->ensureComplete($appraisal, 'score');
                $appraisal->rating_id ??= $this->suggestedRating($overall);
                $appraisal->status = 'submitted';
                $appraisal->submitted_at = now();
            }

            $appraisal->save();

            if ($submit) {
                $employeeUser = Employee::whereKey($appraisal->employee_id)->value('user_id');
                $this->notificationService->notify(array_filter([$employeeUser]), 'appraisal_ready', 'Your appraisal is ready', 'Read it in My HR and acknowledge it with your comments.', route('my-hr'), $appraisal);
                $this->activityLogger->record('appraisals', 'submitted', "{$user->name} submitted the appraisal of ".Employee::find($appraisal->employee_id)?->fullName(), $appraisal);
            }

            return $appraisal->refresh();
        });
    }

    public function acknowledge(Appraisal $appraisal, ?string $comments, User $user): Appraisal
    {
        if ($this->roleOf($appraisal, $user) !== 'employee' || $appraisal->status !== 'submitted') {
            throw ValidationException::withMessages(['appraisal' => ['Only your own submitted appraisal can be acknowledged.']]);
        }

        $appraisal->update(['status' => 'acknowledged', 'employee_comments' => $comments, 'acknowledged_at' => now()]);
        $this->activityLogger->record('appraisals', 'acknowledged', "{$user->name} acknowledged their appraisal", $appraisal);

        return $appraisal;
    }

    public function reopen(Appraisal $appraisal, User $user): Appraisal
    {
        if ($appraisal->status !== 'submitted') {
            throw ValidationException::withMessages(['appraisal' => ['Only a submitted (not yet acknowledged) appraisal can be reopened.']]);
        }

        $appraisal->update(['status' => 'draft', 'submitted_at' => null]);
        $this->activityLogger->record('appraisals', 'reopened', "{$user->name} reopened an appraisal for changes", $appraisal);

        return $appraisal;
    }

    public function reassign(Appraisal $appraisal, ?int $reviewerId, User $user): Appraisal
    {
        if ($reviewerId !== null && $reviewerId === $appraisal->employee_id) {
            throw ValidationException::withMessages(['reviewer_id' => ['Staff cannot review themselves.']]);
        }

        $appraisal->update(['reviewer_id' => $reviewerId]);
        $reviewerUser = Employee::whereKey($reviewerId)->value('user_id');
        $this->notificationService->notify(array_filter([$reviewerUser]), 'appraisal_to_review', 'You have a new appraisal to review', Employee::find($appraisal->employee_id)?->fullName().' — open My HR → Reviews to do.', route('my-hr'), $appraisal);

        return $appraisal;
    }

    /**
     * What the signed-in staff member sees in My HR: their own appraisals and the ones they review.
     *
     * @return array{mine: array<int, array<string, mixed>>, to_review: array<int, array<string, mixed>>}
     */
    public function forStaff(User $user): array
    {
        $employee = Employee::where('user_id', $user->id)->first(['id']);

        if ($employee === null) {
            return ['mine' => [], 'to_review' => []];
        }

        $row = fn (Appraisal $appraisal): array => [
            'id' => $appraisal->id, 'cycle' => $appraisal->cycle?->name, 'status' => $appraisal->status,
            'employee' => $appraisal->employee?->fullName(), 'reviewer' => $appraisal->reviewer?->fullName(),
            'overall_score' => in_array($appraisal->status, ['submitted', 'acknowledged'], true) ? $appraisal->overall_score : null,
            'rating' => in_array($appraisal->status, ['submitted', 'acknowledged'], true) ? $appraisal->rating?->name : null,
        ];
        $with = ['cycle:id,name,status', 'employee:id,first_name,middle_name,last_name', 'reviewer:id,first_name,middle_name,last_name', 'rating:id,name'];

        return [
            'mine' => Appraisal::where('employee_id', $employee->id)->with($with)->latest('id')->limit(20)->get()->map($row)->all(),
            'to_review' => Appraisal::where('reviewer_id', $employee->id)->whereIn('status', ['self_review', 'draft'])
                ->whereHas('cycle', fn (Builder $query) => $query->where('status', 'open'))->with($with)->get()->map($row)->all(),
        ];
    }

    public function pdf(Appraisal $appraisal, User $viewer): Response
    {
        $details = $this->details($appraisal, $viewer);

        return Pdf::loadView('pdf.appraisal', ['appraisal' => $details, 'schoolName' => SystemSetting::query()->value('school_name')])->setPaper('a4')
            ->stream('appraisal-'.str($details['employee']['name'])->slug().'.pdf');
    }

    /**
     * The best rating (first in the list) for a perfect score, sliding down in equal bands.
     */
    public function suggestedRating(?float $score): ?int
    {
        $ratings = Lookup::query()->ofType('appraisal_rating')->active()->orderBy('sort_order')->pluck('id')->values();

        if ($score === null || $ratings->isEmpty()) {
            return null;
        }

        $band = (self::MAX_SCORE - 1) / $ratings->count();
        $index = (int) min($ratings->count() - 1, max(0, floor((self::MAX_SCORE - $score) / $band)));

        return $ratings[$index];
    }

    /**
     * hr (manage-appraisals), reviewer (the assigned reviewer), employee (the person appraised) or null.
     */
    public function roleOf(Appraisal $appraisal, User $user): ?string
    {
        $employeeId = Employee::where('user_id', $user->id)->value('id');

        return match (true) {
            $employeeId !== null && $employeeId === $appraisal->employee_id => 'employee',
            $employeeId !== null && $employeeId === $appraisal->reviewer_id => 'reviewer',
            $user->hasPermission('manage-appraisals') => 'hr',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeScores(Appraisal $appraisal, array $rows, string $scoreField, string $commentField): void
    {
        foreach ($rows as $row) {
            AppraisalScore::where('appraisal_id', $appraisal->id)->whereKey($row['id'])->update([
                $scoreField => isset($row[$scoreField]) ? min(self::MAX_SCORE, max(1, (float) $row[$scoreField])) : null,
                $commentField => $row[$commentField] ?? null,
            ]);
        }
    }

    private function ensureComplete(Appraisal $appraisal, string $field): void
    {
        if (AppraisalScore::where('appraisal_id', $appraisal->id)->whereNull($field)->exists()) {
            throw ValidationException::withMessages(['scores' => ['Score every criterion before submitting.']]);
        }
    }

    /**
     * Weighted mean of the given score field (plain mean when no weights are set).
     */
    private function weighted(Appraisal $appraisal, string $field): ?float
    {
        $rows = AppraisalScore::where('appraisal_id', $appraisal->id)->whereNotNull($field)->with('criterion:id,weight_percent')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $weights = $rows->sum(fn (AppraisalScore $row): float => (float) $row->criterion?->weight_percent);

        return round($weights > 0
            ? $rows->sum(fn (AppraisalScore $row): float => $row->{$field} * (float) $row->criterion?->weight_percent) / $weights
            : (float) $rows->avg($field), 2);
    }
}
