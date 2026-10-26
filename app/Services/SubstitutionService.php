<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\StaffAttendance;
use App\Models\Substitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cover for absent teachers: for a date, every period whose teacher is on approved leave or marked absent /
 * on leave, with free teachers suggested (not away, no class and no other cover in that period; the leave's
 * named substitute first, then whoever teaches the fewest periods that day). The cover teacher is told and
 * sees the period in today's schedule.
 */
class SubstitutionService
{
    public function __construct(
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{date: string, day: string, teachers: array<int, array<string, mixed>>}
     */
    public function day(string $date): array
    {
        $day = Carbon::parse($date);
        $away = $this->awayTeachers($day);
        $slots = $this->slotsOn($day)->with(['period:id,name,starts_at,ends_at,sort_order', 'section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name'])
            ->whereIn('teacher_id', $away->keys())->get()->sortBy(fn (TimetableSlot $slot): int => (int) $slot->period?->sort_order);
        $covers = Substitution::whereDate('date', $day)->with('substitute:id,first_name,middle_name,last_name')->get()->keyBy('timetable_slot_id');
        $teachers = Employee::where('is_teaching_staff', true)->whereIn('status', Employee::CURRENT_STATUSES)->whereNotIn('id', $away->keys())
            ->orderBy('first_name')->get(['id', 'first_name', 'middle_name', 'last_name']);
        $busy = $this->slotsOn($day)->whereNotNull('teacher_id')->get(['teacher_id', 'period_id']);
        $load = $busy->countBy('teacher_id');
        $busyCover = Substitution::whereDate('date', $day)->with('slot:id,period_id')->get(['id', 'timetable_slot_id', 'substitute_teacher_id']);

        return [
            'date' => $day->toDateString(),
            'day' => $day->format('l'),
            'teachers' => $away->map(function (array $reason, int $teacherId) use ($slots, $covers, $teachers, $busy, $busyCover, $load): array {
                $teacherSlots = $slots->where('teacher_id', $teacherId);

                return [
                    'teacher_id' => $teacherId,
                    'name' => $reason['name'],
                    'reason' => $reason['reason'],
                    'periods' => $teacherSlots->map(function (TimetableSlot $slot) use ($covers, $teachers, $busy, $busyCover, $load, $reason): array {
                        $cover = $covers->get($slot->id);
                        $free = $teachers->filter(fn (Employee $teacher): bool => ! $busy->contains(fn ($taken): bool => $taken->teacher_id === $teacher->id && $taken->period_id === $slot->period_id)
                            && ! $busyCover->contains(fn (Substitution $other): bool => $other->substitute_teacher_id === $teacher->id && $other->slot?->period_id === $slot->period_id && $other->timetable_slot_id !== $slot->id))
                            ->sortBy(fn (Employee $teacher): string => ($teacher->id === $reason['substitute_id'] ? '0' : '1').str_pad((string) $load->get($teacher->id, 0), 3, '0', STR_PAD_LEFT).$teacher->first_name);

                        return [
                            'timetable_slot_id' => $slot->id,
                            'period' => $slot->period?->name,
                            'starts_at' => $slot->period?->starts_at ? substr((string) $slot->period->starts_at, 0, 5) : null,
                            'class' => trim(($slot->section?->schoolClass?->name ?? '').' '.($slot->section?->name ?? '')),
                            'subject' => $slot->subject?->name,
                            'cover' => $cover === null ? null : ['id' => $cover->id, 'teacher_id' => $cover->substitute_teacher_id, 'name' => $cover->substitute?->fullName()],
                            'free_teachers' => $free->map(fn (Employee $teacher): array => ['id' => $teacher->id, 'name' => $teacher->fullName(), 'periods_today' => $load->get($teacher->id, 0)])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->filter(fn (array $teacher): bool => $teacher['periods'] !== [])->values()->all(),
        ];
    }

    /**
     * Give a period to a cover teacher (replacing an earlier cover for it).
     *
     * @param  array{date: string, timetable_slot_id: int, substitute_teacher_id: int, remarks?: string|null}  $data
     */
    public function assign(array $data, User $user): Substitution
    {
        $day = Carbon::parse($data['date']);
        $slot = TimetableSlot::with(['period:id,name', 'section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name'])->findOrFail($data['timetable_slot_id']);

        if ($slot->day_of_week !== $day->dayOfWeekIso || $slot->teacher_id === null) {
            throw ValidationException::withMessages(['timetable_slot_id' => ['This period is not on that day.']]);
        }

        $period = collect($this->day($day->toDateString())['teachers'])->flatMap(fn (array $teacher): array => $teacher['periods'])->firstWhere('timetable_slot_id', $slot->id);

        if ($period === null) {
            throw ValidationException::withMessages(['timetable_slot_id' => ['The teacher of this period is not away that day.']]);
        }

        if (! collect($period['free_teachers'])->contains('id', (int) $data['substitute_teacher_id'])) {
            throw ValidationException::withMessages(['substitute_teacher_id' => ['This teacher is not free in that period.']]);
        }

        $substitution = DB::transaction(function () use ($data, $day, $slot): Substitution {
            Substitution::whereDate('date', $day)->where('timetable_slot_id', $slot->id)->get()->each->delete();

            return Substitution::create(['date' => $day->toDateString(), 'timetable_slot_id' => $slot->id, 'absent_teacher_id' => $slot->teacher_id, 'substitute_teacher_id' => $data['substitute_teacher_id'], 'remarks' => $data['remarks'] ?? null]);
        });

        $substitute = Employee::find($data['substitute_teacher_id']);
        $this->notificationService->notify(
            array_filter([$substitute?->user_id]), 'substitution_assigned',
            'Cover period: '.$slot->period?->name.' on '.$day->format('D d M'),
            trim(($slot->section?->schoolClass?->name ?? '').' '.($slot->section?->name ?? '')).' · '.($slot->subject?->name ?? 'Class').($substitution->remarks ? ' · '.$substitution->remarks : ''),
            route('dashboard'), $substitution,
        );

        return $substitution->load('substitute:id,first_name,middle_name,last_name');
    }

    public function remove(Substitution $substitution): void
    {
        DB::transaction(fn () => $substitution->delete());
    }

    /**
     * Periods this teacher covers on a date (for the teacher's schedule and the mobile app).
     *
     * @return Collection<int, Substitution>
     */
    public function coversFor(Employee $teacher, string $date): Collection
    {
        return Substitution::where('substitute_teacher_id', $teacher->id)->whereDate('date', $date)
            ->with(['slot.period:id,name,starts_at,ends_at,sort_order', 'slot.section:id,name,class_id', 'slot.section.schoolClass:id,name', 'slot.subject:id,name'])->get();
    }

    /**
     * Teaching staff away on the date: approved leave, or marked absent / on leave in staff attendance.
     *
     * @return Collection<int, array{name: string, reason: string, substitute_id: int|null}>
     */
    private function awayTeachers(Carbon $day): Collection
    {
        $leave = LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $day)->whereDate('to_date', '>=', $day)->with('leaveType:id,name')->get(['employee_id', 'leave_type_id', 'substitute_employee_id'])->keyBy('employee_id');
        $marked = StaffAttendance::whereDate('date', $day)->whereIn('status', ['absent', 'leave'])->pluck('status', 'employee_id');

        return Employee::where('is_teaching_staff', true)->whereIn('id', $leave->keys()->merge($marked->keys())->unique())
            ->get(['id', 'first_name', 'middle_name', 'last_name'])
            ->mapWithKeys(fn (Employee $teacher): array => [$teacher->id => [
                'name' => $teacher->fullName(),
                'reason' => $leave->has($teacher->id) ? ($leave[$teacher->id]->leaveType?->name ?? 'Leave') : ucfirst((string) $marked[$teacher->id]),
                'substitute_id' => $leave->get($teacher->id)?->substitute_employee_id,
            ]]);
    }

    private function slotsOn(Carbon $day): Builder
    {
        return TimetableSlot::query()->where('day_of_week', $day->dayOfWeekIso)
            ->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day));
    }
}
