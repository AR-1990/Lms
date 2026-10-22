<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ExamHall;
use App\Models\ExamTerm;
use App\Models\GradingScale;
use App\Services\ExamSetupService;
use Illuminate\Database\Seeder;

/**
 * Exam setup the school starts with: a default grading scale, two terms for the current
 * session and two exam halls. All editable under Exams → Setup.
 */
class ExamSeeder extends Seeder
{
    public function run(ExamSetupService $setupService): void
    {
        if (! GradingScale::exists()) {
            $setupService->save('scales', null, [
                'name' => 'Standard Grading',
                'is_default' => true,
                'items' => [
                    ['grade' => 'A+', 'min_percent' => 90, 'max_percent' => 100, 'grade_point' => 4.0, 'remark' => 'Outstanding'],
                    ['grade' => 'A', 'min_percent' => 80, 'max_percent' => 89.99, 'grade_point' => 3.7, 'remark' => 'Excellent'],
                    ['grade' => 'B', 'min_percent' => 70, 'max_percent' => 79.99, 'grade_point' => 3.0, 'remark' => 'Very good'],
                    ['grade' => 'C', 'min_percent' => 60, 'max_percent' => 69.99, 'grade_point' => 2.5, 'remark' => 'Good'],
                    ['grade' => 'D', 'min_percent' => 50, 'max_percent' => 59.99, 'grade_point' => 2.0, 'remark' => 'Satisfactory'],
                    ['grade' => 'E', 'min_percent' => 40, 'max_percent' => 49.99, 'grade_point' => 1.0, 'remark' => 'Needs improvement'],
                    ['grade' => 'F', 'min_percent' => 0, 'max_percent' => 39.99, 'grade_point' => 0, 'remark' => 'Fail'],
                ],
            ]);
        }

        $session = AcademicSession::where('is_current', true)->first();

        if ($session !== null && ! ExamTerm::where('academic_session_id', $session->id)->exists()) {
            $year = (int) $session->starts_on->format('Y');

            foreach ([
                ['First Term', 40, "{$year}-08-01", "{$year}-12-20", false, 10],
                ['Final Term', 60, ($year + 1).'-01-05', $session->ends_on->toDateString(), true, 20],
            ] as [$name, $weight, $from, $to, $final, $order]) {
                ExamTerm::create(['academic_session_id' => $session->id, 'name' => $name, 'weightage_percent' => $weight, 'starts_on' => $from, 'ends_on' => $to, 'is_final' => $final, 'sort_order' => $order]);
            }
        }

        foreach ([['Hall A', 6, 5, 30], ['Room 12', 5, 4, 20]] as [$name, $rows, $columns, $capacity]) {
            ExamHall::firstOrCreate(['name' => $name], ['rows' => $rows, 'columns' => $columns, 'capacity' => $capacity, 'is_active' => true]);
        }
    }
}
