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
            'message' => 'Welcome to LMS',
            'portal' => 'student',
            'user' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ],
        ];
    }

    /**
     * Get enrolled courses.
     */
    public function getEnrolledCourses(User $student): array
    {
        return [
            'courses' => [],
        ];
    }

    /**
     * Get attendance summary and logs.
     */
    public function getAttendance(User $student): array
    {
        return [
            'overall_percentage' => '0%',
            'breakdown' => [],
            'recent_logs' => [],
        ];
    }

    /**
     * Get grades & transcript summary.
     */
    public function getGrades(User $student): array
    {
        return [
            'gpa' => '0.00',
            'semester' => 'Current Semester',
            'grades' => [],
        ];
    }
}
