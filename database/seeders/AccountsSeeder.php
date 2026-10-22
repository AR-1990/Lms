<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\FiscalYear;
use App\Models\Lookup;
use App\Models\NumberSequence;
use App\Models\PaymentMethod;
use App\Models\Vendor;
use App\Models\VoucherType;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const ACCOUNT_TYPES = [
        'Asset' => 'debit',
        'Liability' => 'credit',
        'Equity' => 'credit',
        'Income' => 'credit',
        'Expense' => 'debit',
    ];

    /**
     * Default chart of accounts: [code, name, type, parent code, flags]. Flags: g = group, s = system,
     * c = cash account, b = bank account. Schools rename or extend it from the admin panel.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string|null, 4: string}>
     */
    private const CHART = [
        ['1000', 'Assets', 'Asset', null, 'gs'],
        ['1100', 'Cash in Hand', 'Asset', '1000', 'sc'],
        ['1110', 'Petty Cash', 'Asset', '1000', 'c'],
        ['1200', 'Bank Accounts', 'Asset', '1000', 'gs'],
        ['1210', 'HBL Main Account', 'Asset', '1200', 'b'],
        ['1300', 'Student Receivable', 'Asset', '1000', 's'],
        ['1400', 'Employee Loans & Advances', 'Asset', '1000', 's'],
        ['1500', 'Inventory', 'Asset', '1000', 's'],
        ['1600', 'Fixed Assets', 'Asset', '1000', 'gs'],
        ['1610', 'Furniture & Fixtures', 'Asset', '1600', ''],
        ['1620', 'Computers & IT Equipment', 'Asset', '1600', ''],
        ['1630', 'Vehicles', 'Asset', '1600', ''],
        ['1690', 'Accumulated Depreciation', 'Asset', '1000', 's'],
        ['2000', 'Liabilities', 'Liability', null, 'gs'],
        ['2100', 'Accounts Payable', 'Liability', '2000', 's'],
        ['2200', 'Salaries Payable', 'Liability', '2000', 's'],
        ['2300', 'Student Advances', 'Liability', '2000', 's'],
        ['2400', 'Tax Payable', 'Liability', '2000', 's'],
        ['2500', 'Security Deposits', 'Liability', '2000', ''],
        ['3000', 'Equity', 'Equity', null, 'gs'],
        ['3100', 'Owner\'s Capital', 'Equity', '3000', ''],
        ['3200', 'Retained Earnings', 'Equity', '3000', 's'],
        ['4000', 'Income', 'Income', null, 'gs'],
        ['4100', 'Tuition Fee Income', 'Income', '4000', 's'],
        ['4200', 'Admission Fee Income', 'Income', '4000', 's'],
        ['4300', 'Late Fine Income', 'Income', '4000', 's'],
        ['4400', 'Exam Fee Income', 'Income', '4000', ''],
        ['4900', 'Other Income', 'Income', '4000', ''],
        ['5000', 'Expenses', 'Expense', null, 'gs'],
        ['5100', 'Salary Expense', 'Expense', '5000', 's'],
        ['5200', 'Utilities', 'Expense', '5000', ''],
        ['5300', 'Rent', 'Expense', '5000', ''],
        ['5400', 'Concession & Discount Expense', 'Expense', '5000', 's'],
        ['5500', 'Depreciation Expense', 'Expense', '5000', 's'],
        ['5600', 'Stationery & Printing', 'Expense', '5000', ''],
        ['5700', 'Repairs & Maintenance', 'Expense', '5000', ''],
        ['5900', 'General Expenses', 'Expense', '5000', ''],
    ];

    /**
     * [name, code, number sequence type].
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const VOUCHER_TYPES = [
        ['Journal Voucher', 'JV', 'voucher_jv'],
        ['Receipt Voucher', 'RV', 'voucher_rv'],
        ['Payment Voucher', 'PV', 'voucher_pv'],
        ['Contra Voucher', 'CV', 'voucher_cv'],
        ['Fee Invoice', 'FIV', 'voucher_fiv'],
    ];

    /**
     * [name, code, account code, requires reference, requires bank details, online].
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: bool, 4: bool, 5: bool}>
     */
    private const PAYMENT_METHODS = [
        ['Cash', 'CASH', '1100', false, false, false],
        ['Bank Deposit', 'BANK', '1210', true, false, false],
        ['Cheque', 'CHQ', '1210', true, true, false],
        ['JazzCash', 'JAZZ', '1210', true, false, true],
        ['EasyPaisa', 'EP', '1210', true, false, true],
        ['Debit / Credit Card', 'CARD', '1210', true, false, true],
    ];

    /**
     * Master ledger setup plus a demo bank account and demo vendors. Safe to run again.
     */
    public function run(): void
    {
        $types = collect(self::ACCOUNT_TYPES)->map(fn (string $normalBalance, string $name): int => AccountType::firstOrCreate(
            ['name' => $name],
            ['normal_balance' => $normalBalance],
        )->id);

        $accounts = collect();

        foreach (self::CHART as [$code, $name, $type, $parentCode, $flags]) {
            $accounts[$code] = Account::firstOrCreate(['code' => $code], [
                'name' => $name,
                'account_type_id' => $types[$type],
                'parent_id' => $parentCode ? $accounts[$parentCode]->id : null,
                'is_group' => str_contains($flags, 'g'),
                'is_system' => str_contains($flags, 's'),
                'is_cash_account' => str_contains($flags, 'c'),
                'is_bank_account' => str_contains($flags, 'b'),
                'is_active' => true,
            ]);
        }

        FiscalYear::firstOrCreate(
            ['name' => 'FY 2026-27'],
            ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_current' => true, 'status' => FiscalYear::STATUS_OPEN],
        );

        foreach (self::VOUCHER_TYPES as [$name, $code, $sequenceType]) {
            VoucherType::firstOrCreate(['code' => $code], ['name' => $name, 'number_sequence_type' => $sequenceType, 'is_system' => true]);

            NumberSequence::firstOrCreate(['type' => $sequenceType], [
                'label' => $name,
                'prefix' => $code.'-',
                'include_year' => true,
                'padding' => 5,
                'next_number' => 1,
                'reset_yearly' => true,
                'current_year' => (int) now()->format('Y'),
            ]);
        }

        foreach (self::PAYMENT_METHODS as $position => [$name, $code, $accountCode, $requiresReference, $requiresBankDetails, $isOnline]) {
            PaymentMethod::firstOrCreate(['code' => $code], [
                'name' => $name,
                'account_id' => $accounts[$accountCode]->id,
                'requires_reference' => $requiresReference,
                'requires_bank_details' => $requiresBankDetails,
                'is_online' => $isOnline,
                'is_active' => true,
                'sort_order' => ($position + 1) * 10,
            ]);
        }

        $hbl = Lookup::where('type', 'bank')->where('name', 'HBL')->value('id');

        if ($hbl !== null) {
            BankAccount::firstOrCreate(['account_id' => $accounts['1210']->id], [
                'bank_id' => $hbl,
                'branch' => 'Main Boulevard (demo)',
                'account_title' => 'Pinnacle International Academy',
                'account_number' => '0000-00000000-01',
                'is_default_for_fees' => true,
            ]);
        }

        if (Vendor::count() === 0) {
            Vendor::factory()->count(5)->create(['payable_account_id' => $accounts['2100']->id]);
        }
    }
}
