<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NumberSequenceService;
use Illuminate\Database\Seeder;

/**
 * Expense categories mapped to the expense accounts (master data, editable under Accounts → Expenses) and a
 * few demo bills waiting for approval or payment. Nothing is posted here. Re-runnable.
 */
class ExpenseSeeder extends Seeder
{
    public function run(NumberSequenceService $numberSequenceService): void
    {
        $categories = [];

        foreach ([
            ['Utilities', '5200'],
            ['Rent', '5300'],
            ['Stationery & Printing', '5600'],
            ['Repairs & Maintenance', '5700'],
            ['General Expenses', '5900'],
        ] as [$name, $code]) {
            $categories[$name] = ExpenseCategory::firstOrCreate(['name' => $name], ['expense_account_id' => Account::where('code', $code)->value('id'), 'is_active' => true]);
        }

        if (Expense::exists()) {
            return;
        }

        $vendor = Vendor::query()->value('id');
        $admin = User::where('email', 'admin@lms.test')->value('id');

        foreach ([
            ['Utilities', 'Electricity bill — October', 4850000, 'approved'],
            ['Repairs & Maintenance', 'Plumbing repair, junior block washrooms', 1250000, 'approved'],
            ['General Expenses', 'Refreshments for parent-teacher meeting', 680000, 'draft'],
        ] as [$category, $description, $amount, $status]) {
            Expense::create([
                'expense_no' => $numberSequenceService->next('expense_no'),
                'expense_category_id' => $categories[$category]->id,
                'vendor_id' => $category === 'Repairs & Maintenance' ? $vendor : null,
                'expense_date' => now()->subDays(3)->toDateString(),
                'amount' => $amount,
                'description' => $description,
                'status' => $status,
                'approved_by' => $status === 'approved' ? $admin : null,
                'approved_at' => $status === 'approved' ? now() : null,
            ]);
        }
    }
}
