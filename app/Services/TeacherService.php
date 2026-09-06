<?php

namespace App\Services;

use App\Models\User;

class TeacherService
{
    /**
     * Get Teacher dashboard data.
     */
    public function getTeacherDashboard(User $teacher): array
    {
        return [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
            ],
            'metrics' => [
                'assigned_classes_count' => 4,
                'total_students_count' => 120,
                'pending_assignments_count' => 18,
                'today_attendance_percentage' => '94.5%',
            ],
            'today_schedule' => [
                [
                    'time' => '09:00 AM - 10:30 AM',
                    'subject' => 'Web Development & API Architecture',
                    'room' => 'Lab 3',
                    'students_count' => 32,
                ],
                [
                    'time' => '11:00 AM - 12:30 PM',
                    'subject' => 'Database Design & Management',
                    'room' => 'Room 204',
                    'students_count' => 28,
                ],
            ],
        ];
    }

    /**
     * Get classes assigned to teacher.
     */
    public function getAssignedClasses(User $teacher): array
    {
        return [
            [
                'id' => 1,
                'course_code' => 'CS-301',
                'title' => 'Web Development & API Architecture',
                'section' => 'A',
                'enrolled_students' => 32,
                'semester' => 'Fall 2026',
                'schedule' => 'Mon / Wed 09:00–10:30',
                'room' => 'Lab 3',
            ],
            [
                'id' => 2,
                'course_code' => 'CS-302',
                'title' => 'Database Design & Management',
                'section' => 'B',
                'enrolled_students' => 28,
                'semester' => 'Fall 2026',
                'schedule' => 'Tue / Thu 11:00–12:30',
                'room' => 'Room 204',
            ],
            [
                'id' => 3,
                'course_code' => 'CS-204',
                'title' => 'Object Oriented Programming',
                'section' => 'A',
                'enrolled_students' => 35,
                'semester' => 'Fall 2026',
                'schedule' => 'Fri 09:00–12:00',
                'room' => 'Lab 1',
            ],
            [
                'id' => 4,
                'course_code' => 'CS-110',
                'title' => 'Computing Fundamentals',
                'section' => 'C',
                'enrolled_students' => 25,
                'semester' => 'Fall 2026',
                'schedule' => 'Wed 14:00–15:30',
                'room' => 'Room 118',
            ],
        ];
    }

    /**
     * Attendance register overview for faculty.
     */
    public function getAttendanceRegister(User $teacher): array
    {
        return [
            'summary' => [
                'sessions_today' => 2,
                'marked' => 1,
                'pending' => 1,
                'avg_presence' => '94.5%',
            ],
            'sessions' => [
                [
                    'class' => 'CS-301 · Section A',
                    'date' => '06 Sep 2026',
                    'time' => '09:00 AM',
                    'present' => 30,
                    'absent' => 2,
                    'status' => 'Submitted',
                ],
                [
                    'class' => 'CS-302 · Section B',
                    'date' => '06 Sep 2026',
                    'time' => '11:00 AM',
                    'present' => '—',
                    'absent' => '—',
                    'status' => 'Pending',
                ],
                [
                    'class' => 'CS-204 · Section A',
                    'date' => '05 Sep 2026',
                    'time' => '09:00 AM',
                    'present' => 33,
                    'absent' => 2,
                    'status' => 'Submitted',
                ],
            ],
        ];
    }

    /**
     * Gradebook overview for faculty.
     */
    public function getGradebook(User $teacher): array
    {
        return [
            'summary' => [
                'open_assessments' => 3,
                'drafts' => 1,
                'published' => 5,
                'avg_score' => '78%',
            ],
            'assessments' => [
                [
                    'title' => 'API Architecture Quiz 2',
                    'class' => 'CS-301 · A',
                    'due' => '10 Sep 2026',
                    'submissions' => '28 / 32',
                    'status' => 'Open',
                ],
                [
                    'title' => 'ERD Mini Project',
                    'class' => 'CS-302 · B',
                    'due' => '12 Sep 2026',
                    'submissions' => '15 / 28',
                    'status' => 'Open',
                ],
                [
                    'title' => 'OOP Lab Midterm',
                    'class' => 'CS-204 · A',
                    'due' => '08 Sep 2026',
                    'submissions' => '35 / 35',
                    'status' => 'Published',
                ],
                [
                    'title' => 'Computing Fundamentals Test 1',
                    'class' => 'CS-110 · C',
                    'due' => '15 Sep 2026',
                    'submissions' => '0 / 25',
                    'status' => 'Draft',
                ],
            ],
        ];
    }

    /**
     * Record attendance for a class.
     */
    public function recordAttendance(User $teacher, array $data): array
    {
        return [
            'class_id' => $data['class_id'],
            'date' => $data['date'] ?? now()->toDateString(),
            'recorded_by' => $teacher->name,
            'total_students' => count($data['attendance'] ?? []),
            'status' => 'Attendance successfully submitted',
        ];
    }

    /**
     * Submit grades for students.
     */
    public function submitGrades(User $teacher, array $data): array
    {
        return [
            'class_id' => $data['class_id'],
            'assessment_title' => $data['assessment_title'] ?? 'Mid-Term Exam',
            'submitted_by' => $teacher->name,
            'records_updated' => count($data['grades'] ?? []),
            'status' => 'Grades successfully updated',
        ];
    }
}
