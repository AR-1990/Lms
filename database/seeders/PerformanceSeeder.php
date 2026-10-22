<?php

namespace Database\Seeders;

use App\Models\AssessmentSkill;
use App\Models\Lookup;
use App\Models\PerformanceAlertRule;
use Illuminate\Database\Seeder;

/**
 * Starting co-scholastic skills and "at risk" rules; both are edited under Performance → Setup.
 */
class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $group = fn (string $name): ?int => Lookup::where('type', 'skill_group')->where('name', $name)->value('id');

        foreach ([
            ['Reading Fluency', 'Academic'], ['Communication', 'Social'], ['Teamwork', 'Social'],
            ['Creativity', 'Personal'], ['Discipline', 'Personal'], ['Sports', 'Physical'],
        ] as $position => [$name, $groupName]) {
            AssessmentSkill::firstOrCreate(['name' => $name], ['skill_group_id' => $group($groupName), 'sort_order' => ($position + 1) * 10, 'is_active' => true]);
        }

        foreach ([
            ['Low attendance', 'attendance_percent', '<', 75],
            ['Falling marks', 'average_percent', '<', 50],
            ['Homework not done', 'homework_completion_percent', '<', 60],
            ['Repeated discipline remarks', 'discipline_count', '>=', 3],
        ] as [$name, $metric, $operator, $threshold]) {
            PerformanceAlertRule::firstOrCreate(['name' => $name], ['metric' => $metric, 'operator' => $operator, 'threshold' => $threshold, 'notify_class_teacher' => true, 'notify_parents' => true, 'is_active' => true]);
        }
    }
}
