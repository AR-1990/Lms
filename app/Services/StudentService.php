<?php

namespace App\Services;

use App\Models\User;

class StudentService
{
    /**
     * Get Student portal dashboard.
     */
    public function getStudentDashboard(User $student): array
    {
        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ],
            'academic_summary' => [
                'current_semester' => 'Semester 5 (Fall 2026)',
                'cgpa' => '3.78',
                'enrolled_courses_count' => 5,
                'overall_attendance' => '96.2%',
            ],
            'upcoming_exams' => [
                [
                    'course' => 'Web Development & API Architecture',
                    'date' => '2026-09-15',
                    'time' => '10:00 AM',
                    'venue' => 'Hall A',
                ],
                [
                    'course' => 'Database Design & Management',
                    'date' => '2026-09-18',
                    'time' => '02:00 PM',
                    'venue' => 'Lab 2',
                ],
            ],
        ];
    }

    /**
     * Get enrolled courses.
     */
    public function getEnrolledCourses(User $student): array
    {
        return [
            [
                'code' => 'CS-301',
                'title' => 'Web Development & API Architecture',
                'instructor' => 'Prof. Alex Smith',
                'credit_hours' => 3,
                'progress' => '65%',
                'schedule' => 'Mon / Wed 09:00–10:30',
                'status' => 'In Progress',
            ],
            [
                'code' => 'CS-302',
                'title' => 'Database Design & Management',
                'instructor' => 'Dr. Maria Khan',
                'credit_hours' => 4,
                'progress' => '70%',
                'schedule' => 'Tue / Thu 11:00–12:30',
                'status' => 'In Progress',
            ],
            [
                'code' => 'CS-204',
                'title' => 'Object Oriented Programming',
                'instructor' => 'Engr. Daniel Lee',
                'credit_hours' => 3,
                'progress' => '80%',
                'schedule' => 'Fri 09:00–12:00',
                'status' => 'In Progress',
            ],
            [
                'code' => 'ENG-210',
                'title' => 'Technical Communication',
                'instructor' => 'Ms. Amina Raza',
                'credit_hours' => 2,
                'progress' => '55%',
                'schedule' => 'Thu 14:00–15:30',
                'status' => 'In Progress',
            ],
            [
                'code' => 'MATH-220',
                'title' => 'Discrete Mathematics',
                'instructor' => 'Dr. Imran Saeed',
                'credit_hours' => 3,
                'progress' => '72%',
                'schedule' => 'Mon 14:00–16:30',
                'status' => 'In Progress',
            ],
        ];
    }

    /**
     * Get attendance summary and logs.
     */
    public function getAttendance(User $student): array
    {
        return [
            'overall_percentage' => '96.2%',
            'breakdown' => [
                ['course' => 'CS-301', 'total_classes' => 24, 'attended' => 23, 'percentage' => '95.8%'],
                ['course' => 'CS-302', 'total_classes' => 28, 'attended' => 27, 'percentage' => '96.4%'],
                ['course' => 'CS-204', 'total_classes' => 22, 'attended' => 21, 'percentage' => '95.5%'],
                ['course' => 'ENG-210', 'total_classes' => 18, 'attended' => 18, 'percentage' => '100%'],
                ['course' => 'MATH-220', 'total_classes' => 20, 'attended' => 19, 'percentage' => '95.0%'],
            ],
            'recent_logs' => [
                ['date' => '05 Sep 2026', 'course' => 'CS-301', 'status' => 'Present'],
                ['date' => '05 Sep 2026', 'course' => 'MATH-220', 'status' => 'Present'],
                ['date' => '04 Sep 2026', 'course' => 'CS-302', 'status' => 'Present'],
                ['date' => '04 Sep 2026', 'course' => 'ENG-210', 'status' => 'Present'],
                ['date' => '03 Sep 2026', 'course' => 'CS-204', 'status' => 'Absent'],
            ],
        ];
    }

    /**
     * Get grades & transcript summary.
     */
    public function getGrades(User $student): array
    {
        return [
            'gpa' => '3.78',
            'semester' => 'Semester 5 (Fall 2026)',
            'grades' => [
                ['course_code' => 'CS-301', 'course_title' => 'Web Development', 'assessment' => 'Quiz 1', 'grade' => 'A', 'grade_points' => 4.0],
                ['course_code' => 'CS-302', 'course_title' => 'Database Design', 'assessment' => 'Assignment 2', 'grade' => 'A-', 'grade_points' => 3.7],
                ['course_code' => 'CS-204', 'course_title' => 'OOP', 'assessment' => 'Lab Midterm', 'grade' => 'A', 'grade_points' => 4.0],
                ['course_code' => 'ENG-210', 'course_title' => 'Technical Communication', 'assessment' => 'Essay 1', 'grade' => 'B+', 'grade_points' => 3.3],
                ['course_code' => 'MATH-220', 'course_title' => 'Discrete Mathematics', 'assessment' => 'Quiz 2', 'grade' => 'A-', 'grade_points' => 3.7],
            ],
        ];
    }
}
