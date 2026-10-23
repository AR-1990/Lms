<?php

namespace Database\Seeders;

use App\Models\AppraisalCriterion;
use App\Models\AppraisalCycle;
use Illuminate\Database\Seeder;

/**
 * The appraisal criteria master list (editable under HR → Appraisals) and this year's review cycle left in
 * draft so HR can adjust it and press Launch. Re-runnable.
 */
class AppraisalSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Punctuality & Attendance', 'Arrives on time, keeps leave within policy and is present for duties.', 15, 'all'],
            ['Professional Conduct', 'Courtesy, dress code, confidentiality and following school policies.', 15, 'all'],
            ['Teamwork & Communication', 'Works well with colleagues, parents and management.', 15, 'all'],
            ['Teaching Quality', 'Lesson planning, classroom management and use of teaching aids.', 30, 'teaching'],
            ['Student Outcomes', 'Results, progress of weak students and timely checking of work.', 25, 'teaching'],
            ['Job Knowledge', 'Knows the role well and completes tasks accurately.', 30, 'non_teaching'],
            ['Initiative & Reliability', 'Takes ownership, meets deadlines and needs little supervision.', 25, 'non_teaching'],
        ] as $order => [$name, $description, $weight, $appliesTo]) {
            AppraisalCriterion::firstOrCreate(['name' => $name], ['description' => $description, 'weight_percent' => $weight, 'applies_to' => $appliesTo, 'sort_order' => $order, 'is_active' => true]);
        }

        $year = now()->year;

        AppraisalCycle::firstOrCreate(['name' => "Annual Review {$year}"], [
            'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString(),
            'self_review' => true, 'status' => 'draft',
        ]);
    }
}
