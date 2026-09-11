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
            'message' => 'Welcome to LMS',
            'portal' => 'teacher',
            'user' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
            ],
        ];
    }

    /**
     * Get classes assigned to teacher.
     */
    public function getAssignedClasses(User $teacher): array
    {
        return [
            'classes' => [],
        ];
    }

    /**
     * Attendance register overview for faculty.
     */
    public function getAttendanceRegister(User $teacher): array
    {
        return [
            'summary' => [
                'sessions_today' => 0,
                'marked' => 0,
                'pending' => 0,
                'avg_presence' => '0%',
            ],
            'sessions' => [],
        ];
    }

    /**
     * Gradebook overview for faculty.
     */
    public function getGradebook(User $teacher): array
    {
        return [
            'summary' => [
                'open_assessments' => 0,
                'drafts' => 0,
                'published' => 0,
                'avg_score' => '0%',
            ],
            'assessments' => [],
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
