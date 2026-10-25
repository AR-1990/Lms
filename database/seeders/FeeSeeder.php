<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Account;
use App\Models\ConcessionType;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\LateFeeRule;
use App\Models\Lookup;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeeSeeder extends Seeder
{
    /**
     * [name, code, income account code, frequency, refundable, concession allowed].
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: bool}>
     */
    private const HEADS = [
        ['Registration Fee', 'REG', '4200', 'one_time', false, false],
        ['Admission Fee', 'ADM', '4200', 'one_time', false, true],
        ['Security Deposit', 'SEC', '2500', 'one_time', true, false],
        ['Annual Charges', 'ANN', '4900', 'yearly', false, true],
        ['Tuition Fee', 'TUI', '4100', 'monthly', false, true],
        ['Computer Lab Fee', 'LAB', '4100', 'monthly', false, true],
        ['Exam Fee', 'EXM', '4400', 'one_time', false, false],
        ['Transport Fee', 'TRN', '4900', 'monthly', false, false],
        ['Uniform & Books', 'UNB', '4500', 'one_time', false, false],
        ['Late Payment Fine', 'LPF', '4300', 'one_time', false, false],
    ];

    /**
     * [name, calculation, default value (percent × 100), requires approval].
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: bool}>
     */
    private const CONCESSIONS = [
        ['Sibling', 'percent', 1000, false],
        ['Staff Child', 'percent', 5000, true],
        ['Merit Scholarship', 'percent', 2500, true],
        ['Need-based', 'percent', 2000, true],
        ['Hafiz-e-Quran', 'percent', 1500, true],
    ];

    /**
     * Monthly tuition in rupees per wing (demo amounts; each school edits its own).
     *
     * @var array<string, int>
     */
    private const TUITION = ['Early Years' => 6000, 'Junior School' => 7500, 'Middle School' => 9000, 'Senior School' => 11000];

    /**
     * Fee heads, concession types and a demo fee structure for every class of the current session.
     */
    public function run(): void
    {
        $accounts = Account::pluck('id', 'code');

        $heads = collect(self::HEADS)->values()->mapWithKeys(fn (array $head, int $index): array => [
            $head[1] => FeeHead::firstOrCreate(['code' => $head[1]], [
                'name' => $head[0],
                'income_account_id' => $accounts[$head[2]],
                'frequency_id' => Lookup::where('type', 'fee_frequency')->where('name', Str::headline($head[3]))->value('id'),
                'is_refundable' => $head[4],
                'is_concession_allowed' => $head[5],
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]),
        ]);

        foreach (self::CONCESSIONS as [$name, $calculation, $value, $requiresApproval]) {
            ConcessionType::firstOrCreate(['name' => $name], [
                'calculation' => $calculation,
                'default_value' => $value,
                'expense_account_id' => $accounts['5400'],
                'requires_approval' => $requiresApproval,
                'is_active' => true,
            ]);
        }

        // A starter rule the school switches on (Fees → Late Fees) once it has chosen its own amount.
        LateFeeRule::firstOrCreate(['name' => 'Late payment fine'], [
            'grace_days' => 5, 'calculation' => 'fixed', 'amount' => 50000, 'fee_head_id' => $heads['LPF']->id,
            'invoice_type' => 'monthly', 'is_active' => false,
        ]);

        $session = AcademicSession::where('is_current', true)->first();

        if ($session === null) {
            return;
        }

        foreach (SchoolClass::with('wing')->orderBy('level_order')->get() as $class) {
            if (FeeStructure::where('academic_session_id', $session->id)->where('class_id', $class->id)->exists()) {
                continue;
            }

            $structure = FeeStructure::create([
                'academic_session_id' => $session->id,
                'class_id' => $class->id,
                'name' => $class->name.' — '.$session->name,
                'is_active' => true,
            ]);

            $tuition = self::TUITION[$class->wing?->name ?? 'Junior School'] ?? 7500;
            $hasLab = ! in_array($class->wing?->name, ['Early Years'], true);

            $items = [
                ['REG', 'on_application', 1000, null, 7],
                ['ADM', 'on_admission', 15000, null, 10],
                ['SEC', 'on_admission', 5000, null, 10],
                ['ANN', 'on_admission', 6000, null, 10],
                ['TUI', 'recurring', $tuition, 10, null],
                ...($hasLab ? [['LAB', 'recurring', 500, 10, null]] : []),
                ['EXM', 'per_exam', 1500, null, 7],
            ];

            foreach ($items as $position => [$code, $chargeOn, $rupees, $dueDay, $dueInDays]) {
                $structure->items()->create([
                    'fee_head_id' => $heads[$code]->id,
                    'charge_on' => $chargeOn,
                    'amount' => $rupees * 100,
                    'due_day' => $dueDay,
                    'due_in_days' => $dueInDays,
                    'is_mandatory' => true,
                    'sort_order' => ($position + 1) * 10,
                ]);
            }
        }
    }
}
