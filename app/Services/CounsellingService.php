<?php

namespace App\Services;

use App\Models\CounsellingNote;
use App\Models\Employee;
use App\Models\PerformanceAlert;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Student counselling (PLAN.md §20): sessions with concern, notes, action plan and follow-up. A session can
 * answer an at-risk performance alert. Confidential notes stay with counselling staff; the rest are shown to
 * the student and their parents in the performance tracker.
 */
class CounsellingService
{
    public function __construct(private NotificationService $notificationService, private ActivityLogger $activityLogger) {}

    /**
     * @param  array{concern_id?: int|null, follow_up?: string|null, student_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        $query = CounsellingNote::query()
            ->select(['id', 'student_id', 'session_date', 'counsellor_id', 'concern_id', 'performance_alert_id', 'notes', 'follow_up_on', 'follow_up_done_at', 'parents_met', 'is_confidential', 'created_at'])
            ->with(['student:id,first_name,middle_name,last_name,admission_no', 'student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name', 'counsellor:id,first_name,middle_name,last_name', 'concern:id,name'])
            ->when($filters['concern_id'] ?? null, fn (Builder $query, int $id) => $query->where('concern_id', $id))
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $id) => $query->where('student_id', $id))
            ->when(($filters['follow_up'] ?? null) === 'due', fn (Builder $query) => $query->whereNotNull('follow_up_on')->whereNull('follow_up_done_at')->whereDate('follow_up_on', '<=', today()))
            ->when(($filters['follow_up'] ?? null) === 'upcoming', fn (Builder $query) => $query->whereNull('follow_up_done_at')->whereDate('follow_up_on', '>', today()));

        return DataTables::eloquent($query)
            ->addColumn('student_name', fn (CounsellingNote $row): ?string => $row->student?->fullName())
            ->addColumn('admission_no', fn (CounsellingNote $row): ?string => $row->student?->admission_no)
            ->addColumn('class_label', fn (CounsellingNote $row): string => $this->classOf($row->student))
            ->addColumn('counsellor_name', fn (CounsellingNote $row): ?string => $row->counsellor?->fullName())
            ->addColumn('concern_name', fn (CounsellingNote $row): ?string => $row->concern?->name)
            ->editColumn('session_date', fn (CounsellingNote $row): ?string => $row->session_date?->toDateString())
            ->editColumn('follow_up_on', fn (CounsellingNote $row): ?string => $row->follow_up_on?->toDateString())
            ->editColumn('notes', fn (CounsellingNote $row): string => str($row->notes)->limit(90)->toString())
            ->filterColumn('student_name', fn (Builder $query, string $keyword) => $query->whereHas('student', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%")->orWhere('admission_no', 'like', "%{$keyword}%"))))
            ->filterColumn('counsellor_name', fn (Builder $query, string $keyword) => $query->whereHas('counsellor', fn (Builder $inner) => $inner->where(fn (Builder $match) => $match->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%"))))
            ->removeColumn('student', 'counsellor', 'concern')
            ->toArray();
    }

    /**
     * Record or correct a session. Linking an open alert marks it acknowledged with the session as the action.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?CounsellingNote $note, array $data, User $user): CounsellingNote
    {
        $alert = isset($data['performance_alert_id']) ? PerformanceAlert::find($data['performance_alert_id']) : null;

        if ($alert !== null && $alert->student_id !== (int) $data['student_id']) {
            throw ValidationException::withMessages(['performance_alert_id' => ['That alert belongs to another student.']]);
        }

        return DB::transaction(function () use ($note, $data, $alert, $user): CounsellingNote {
            $note ??= new CounsellingNote;
            $followUpChanged = $note->exists && ($data['follow_up_on'] ?? null) !== $note->follow_up_on?->toDateString();
            $note->fill([...$data, 'parents_met' => $data['parents_met'] ?? false, 'is_confidential' => $data['is_confidential'] ?? true]);

            if ($followUpChanged) {
                $note->follow_up_done_at = null;
            }

            $note->save();

            if ($alert !== null && $alert->status === 'open') {
                $alert->update(['status' => 'acknowledged', 'action_taken' => $alert->action_taken ?? 'Counselling session on '.$note->session_date->format('d M Y')]);
                $this->activityLogger->record('counselling', 'alert_acknowledged', "{$user->name} answered a performance alert with a counselling session", $alert);
            }

            return $note;
        });
    }

    public function delete(CounsellingNote $note): void
    {
        $note->delete();
    }

    public function completeFollowUp(CounsellingNote $note, User $user): CounsellingNote
    {
        if ($note->follow_up_on === null) {
            throw ValidationException::withMessages(['follow_up_on' => ['This session has no follow-up.']]);
        }

        $note->update(['follow_up_done_at' => now()]);
        $this->activityLogger->record('counselling', 'follow_up_done', "{$user->name} marked a counselling follow-up done", $note);

        return $note;
    }

    /**
     * One session in full, with the student's earlier sessions for context.
     *
     * @return array<string, mixed>
     */
    public function details(CounsellingNote $note): array
    {
        $note->load(['student:id,first_name,middle_name,last_name,admission_no', 'student.currentEnrollment.schoolClass:id,name', 'student.currentEnrollment.section:id,name', 'counsellor:id,first_name,middle_name,last_name', 'concern:id,name', 'alert.rule:id,name']);

        return [
            ...$note->only(['id', 'student_id', 'counsellor_id', 'concern_id', 'performance_alert_id', 'notes', 'action_plan', 'parents_met', 'is_confidential']),
            'session_date' => $note->session_date?->toDateString(),
            'follow_up_on' => $note->follow_up_on?->toDateString(),
            'follow_up_done_at' => $note->follow_up_done_at?->toDateString(),
            'student_name' => $note->student?->fullName(),
            'admission_no' => $note->student?->admission_no,
            'class_label' => $this->classOf($note->student),
            'counsellor_name' => $note->counsellor?->fullName(),
            'concern_name' => $note->concern?->name,
            'alert' => $note->alert?->rule?->name,
            'history' => $this->history($note->student_id, $note->id),
        ];
    }

    /**
     * Earlier sessions of a student, newest first (counselling staff only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $studentId, ?int $exceptId = null): array
    {
        return CounsellingNote::where('student_id', $studentId)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->with(['counsellor:id,first_name,middle_name,last_name', 'concern:id,name'])->latest('session_date')->latest('id')->limit(30)
            ->get(['id', 'session_date', 'counsellor_id', 'concern_id', 'notes', 'action_plan', 'is_confidential'])
            ->map(fn (CounsellingNote $row): array => [
                'id' => $row->id, 'session_date' => $row->session_date?->toDateString(), 'concern' => $row->concern?->name,
                'counsellor' => $row->counsellor?->fullName(), 'notes' => $row->notes, 'action_plan' => $row->action_plan, 'is_confidential' => $row->is_confidential,
            ])->all();
    }

    /**
     * What the student and their parents may read: sessions not marked confidential.
     *
     * @return array<int, array<string, mixed>>
     */
    public function shared(Student $student, int $limit = 20): array
    {
        return CounsellingNote::where('student_id', $student->id)->where('is_confidential', false)
            ->with(['counsellor:id,first_name,middle_name,last_name', 'concern:id,name'])->latest('session_date')->limit($limit)
            ->get(['id', 'session_date', 'counsellor_id', 'concern_id', 'notes', 'action_plan', 'follow_up_on'])
            ->map(fn (CounsellingNote $row): array => [
                'session_date' => $row->session_date?->toDateString(), 'concern' => $row->concern?->name, 'counsellor' => $row->counsellor?->fullName(),
                'notes' => $row->notes, 'action_plan' => $row->action_plan, 'follow_up_on' => $row->follow_up_on?->toDateString(),
            ])->all();
    }

    /**
     * Daily: tell each counsellor about the follow-ups due today.
     */
    public function sendFollowUpReminders(): int
    {
        $due = CounsellingNote::whereDate('follow_up_on', today())->whereNull('follow_up_done_at')
            ->with(['student:id,first_name,middle_name,last_name', 'counsellor:id,user_id'])->get(['id', 'student_id', 'counsellor_id']);

        foreach ($due->groupBy('counsellor_id') as $notes) {
            $userId = $notes->first()->counsellor?->user_id;

            if ($userId !== null) {
                $names = $notes->map(fn (CounsellingNote $note): ?string => $note->student?->fullName())->filter()->implode(', ');
                $this->notificationService->notify([$userId], 'counselling_follow_up', 'Counselling follow-ups today', $names, route('admin.performance.counselling'), $notes->first());
            }
        }

        return $due->count();
    }

    /**
     * Counsellors to choose from; the signed-in staff member comes first.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function counsellors(User $user): array
    {
        $ownId = Employee::where('user_id', $user->id)->value('id');

        return Employee::whereIn('status', Employee::CURRENT_STATUSES)->orderByRaw('id = ? desc', [$ownId ?? 0])->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name'])->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->fullName()])->all();
    }

    private function classOf(?Student $student): string
    {
        return trim(($student?->currentEnrollment?->schoolClass?->name ?? '').' '.($student?->currentEnrollment?->section?->name ?? ''));
    }
}
