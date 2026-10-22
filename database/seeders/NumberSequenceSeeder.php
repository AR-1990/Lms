<?php

namespace Database\Seeders;

use App\Models\NumberSequence;
use Illuminate\Database\Seeder;

class NumberSequenceSeeder extends Seeder
{
    /**
     * Number formats used by the modules in PLAN.md: [type, label, prefix, include_year, padding, reset_yearly].
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: bool, 4: int, 5: bool}>
     */
    private const SEQUENCES = [
        ['inquiry_no', 'Admission inquiry', 'INQ-', true, 5, true],
        ['application_no', 'Admission application', 'APP-', true, 5, true],
        ['admission_no', 'Student admission number', 'PIA-', true, 5, false],
        ['employee_code', 'Employee code', 'EMP-', false, 4, false],
        ['job_application_no', 'Job application', 'JOB-', true, 4, true],
        ['fee_invoice', 'Fee invoice / challan', 'INV-', true, 6, true],
        ['receipt', 'Fee receipt', 'RCP-', true, 6, true],
        ['voucher_jv', 'Journal voucher', 'JV-', true, 5, true],
        ['voucher_rv', 'Receipt voucher', 'RV-', true, 5, true],
        ['voucher_pv', 'Payment voucher', 'PV-', true, 5, true],
        ['voucher_cv', 'Contra voucher', 'CV-', true, 5, true],
        ['expense_no', 'Expense', 'EXP-', true, 5, true],
        ['asset_tag', 'Asset tag', 'AST-', false, 5, false],
        ['purchase_request_no', 'Purchase request', 'PR-', true, 5, true],
        ['po_no', 'Purchase order', 'PO-', true, 5, true],
        ['grn_no', 'Goods receipt (GRN)', 'GRN-', true, 5, true],
        ['issue_no', 'Stock issue', 'ISS-', true, 5, true],
        ['task_no', 'Task', 'TSK-', false, 5, false],
        ['id_card', 'ID card', 'CARD-', false, 6, false],
    ];

    /**
     * Run the database seeds. Existing counters are never reset by re-seeding.
     */
    public function run(): void
    {
        NumberSequence::withoutEvents(function (): void {
            foreach (self::SEQUENCES as [$type, $label, $prefix, $includeYear, $padding, $resetYearly]) {
                NumberSequence::firstOrCreate(
                    ['type' => $type],
                    [
                        'label' => $label,
                        'prefix' => $prefix,
                        'include_year' => $includeYear,
                        'padding' => $padding,
                        'next_number' => 1,
                        'reset_yearly' => $resetYearly,
                        'current_year' => (int) now()->format('Y'),
                    ],
                );
            }
        });
    }
}
