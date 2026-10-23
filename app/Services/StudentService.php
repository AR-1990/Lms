<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\ExamSubject;
use App\Models\FeeInvoice;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * The signed-in student's own view (also used for each child in the parent portal): profile, subjects,
 * attendance, published results and fee balance, all read from the school records.
 */
class StudentService
{
    public function __construct(private AttendanceService $attendanceService, private ResultService $resultService) {}

    /**
     * @return array{student: array<string, mixed>|null, academic_summary: array<string, mixed>, upcoming_exams: array<int, array<string, mixed>>}
     */
    public function getStudentDashboard(User $user): array
    {
        $student = $this->studentFor($user);

        if ($student === null) {
            return ['student' => null, 'academic_summary' => [], 'upcoming_exams' => []];
        }

        $classId = $student->currentEnrollment?->class_id;

        return [
            'student' => $this->profile($student),
            'academic_summary' => $this->summary($student),
            'upcoming_exams' => $classId === null ? [] : ExamSubject::whereDate('exam_date', '>=', today())
                ->whereHas('exam', fn ($query) => $query->where('class_id', $classId)->where('status', '!=', 'draft'))
                ->with(['subject:id,name', 'exam:id,name'])
                ->orderBy('exam_date')->orderBy('starts_at')->limit(8)
                ->get(['id', 'exam_id', 'subject_id', 'exam_date', 'starts_at'])
                ->map(fn (ExamSubject $paper): array => [
                    'exam' => $paper->exam?->name,
                    'subject' => $paper->subject?->name,
                    'date' => $paper->exam_date?->toDateString(),
                    'starts_at' => $paper->starts_at ? substr((string) $paper->starts_at, 0, 5) : null,
                ])->all(),
        ];
    }

    /**
     * Subjects of the student's class this session, with the teacher of each in their section.
     *
     * @return array{courses: array<int, array<string, mixed>>}
     */
    public function getEnrolledCourses(User $user): array
    {
        $enrollment = $this->studentFor($user)?->currentEnrollment;

        if ($enrollment === null) {
            return ['courses' => []];
        }

        $teachers = TeacherAssignment::where('section_id', $enrollment->section_id)->whereNotNull('subject_id')
            ->with('employee:id,first_name,middle_name,last_name')->get()->keyBy('subject_id');

        return ['courses' => ClassSubject::where('class_id', $enrollment->class_id)->where('academic_session_id', $enrollment->academic_session_id)
            ->with('subject:id,name,code')->orderBy('sort_order')->get()
            ->map(fn (ClassSubject $row): array => [
                'code' => $row->subject?->code,
                'title' => $row->subject?->name,
                'teacher' => $teachers->get($row->subject_id)?->employee?->fullName(),
                'periods_per_week' => $row->periods_per_week,
                'is_optional' => (bool) $row->is_optional,
            ])->all()];
    }

    /**
     * Attendance from the start of the session to today, with the latest days first.
     *
     * @return array<string, mixed>
     */
    public function getAttendance(User $user): array
    {
        $student = $this->studentFor($user);

        if ($student === null) {
            return ['percentage' => null, 'counts' => [], 'recent' => []];
        }

        $record = $this->attendanceService->forStudent($student, $this->sessionStart($student), today()->toDateString());

        return ['percentage' => $record['percentage'], 'counts' => $record['counts'], 'recent' => array_slice(array_reverse($record['days']), 0, 30)];
    }

    /**
     * Published results, newest first, with subject marks.
     *
     * @return array{latest: array<string, mixed>|null, results: array<int, array<string, mixed>>}
     */
    public function getGrades(User $user): array
    {
        $student = $this->studentFor($user);
        $results = $student === null ? [] : $this->resultService->forStudent($student);

        return ['latest' => $results[0] ?? null, 'results' => $results];
    }

    public function studentFor(User $user): ?Student
    {
        return Student::where('user_id', $user->id)->with(['currentEnrollment.section:id,name,class_id', 'currentEnrollment.schoolClass:id,name', 'currentEnrollment.academicSession:id,starts_on'])->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(Student $student): array
    {
        $enrollment = $student->currentEnrollment;

        return [
            'id' => $student->id,
            'name' => $student->fullName(),
            'admission_no' => $student->admission_no,
            'class' => $enrollment?->schoolClass?->name,
            'section' => $enrollment?->section?->name,
            'roll_no' => $enrollment?->roll_no,
        ];
    }

    /**
     * Attendance this session, the latest published result and the fee still owed.
     *
     * @return array{attendance_percentage: float|null, latest_result: array<string, mixed>|null, fee_balance: int, open_invoices: int, bus: array<string, mixed>|null}
     */
    public function summary(Student $student): array
    {
        $result = ReportCard::where('student_id', $student->id)->whereHas('exam', fn ($query) => $query->where('status', 'published'))
            ->with('exam:id,name')->latest('id')->first(['id', 'exam_id', 'percentage', 'grade', 'result']);
        $open = FeeInvoice::where('student_id', $student->id)->whereIn('status', FeeInvoice::OPEN_STATUSES);

        return [
            'attendance_percentage' => $this->attendanceService->forStudent($student, $this->sessionStart($student), today()->toDateString())['percentage'],
            'latest_result' => $result === null ? null : ['exam' => $result->exam?->name, 'percentage' => $result->percentage, 'grade' => $result->grade, 'result' => $result->result],
            'fee_balance' => (int) (clone $open)->sum('balance'),
            'open_invoices' => (clone $open)->count(),
            'bus' => app(TransportService::class)->busFor($student),
            'counselling' => app(CounsellingService::class)->shared($student, 3),
        ];
    }

    private function sessionStart(Student $student): string
    {
        return $student->currentEnrollment?->academicSession?->starts_on?->toDateString() ?? today()->startOfYear()->toDateString();
    }
}
