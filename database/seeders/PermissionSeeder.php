<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::upsert([
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'group' => 'users', 'description' => 'Create and maintain user accounts.'],
            ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'group' => 'roles', 'description' => 'Create roles and assign permissions.'],
            ['name' => 'View System Stats', 'slug' => 'view-stats', 'group' => 'admin', 'description' => 'Review system-wide dashboard statistics.'],
            ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'group' => 'admin', 'description' => 'Configure school-wide system settings.'],
            ['name' => 'Manage Classes', 'slug' => 'manage-classes', 'group' => 'academics', 'description' => 'Maintain teacher class operations.'],
            ['name' => 'Record Attendance', 'slug' => 'record-attendance', 'group' => 'academics', 'description' => 'Record daily attendance.'],
            ['name' => 'Submit Grades', 'slug' => 'submit-grades', 'group' => 'academics', 'description' => 'Submit course grades.'],
            ['name' => 'View Enrolled Courses', 'slug' => 'view-courses', 'group' => 'student', 'description' => 'View enrolled course list.'],
            ['name' => 'View Attendance', 'slug' => 'view-attendance', 'group' => 'student', 'description' => 'View attendance records.'],
            ['name' => 'View Grades', 'slug' => 'view-grades', 'group' => 'student', 'description' => 'View academic grades.'],
            ['name' => 'View Children', 'slug' => 'view-children', 'group' => 'parent', 'description' => 'View linked child profiles.'],
            ['name' => 'View Fee Status', 'slug' => 'view-fees', 'group' => 'parent', 'description' => 'View fee status and challans.'],
            ['name' => 'View Notices', 'slug' => 'view-notices', 'group' => 'parent', 'description' => 'View school notices.'],
            ['name' => 'Manage Fee Collections', 'slug' => 'manage-fees', 'group' => 'accounts', 'description' => 'Manage fee collection workflows.'],
            ['name' => 'View Payroll', 'slug' => 'view-payroll', 'group' => 'accounts', 'description' => 'View payroll information.'],
            ['name' => 'Manage Lookups', 'slug' => 'manage-lookups', 'group' => 'admin', 'description' => 'Maintain dropdown master data (lookups).'],
            ['name' => 'View Activity Log', 'slug' => 'view-activity-log', 'group' => 'admin', 'description' => 'See who did what, on which module.'],
            ['name' => 'Manage Campuses', 'slug' => 'manage-campuses', 'group' => 'admin', 'description' => 'Create and maintain campuses / branches.'],
            ['name' => 'Manage Numbering', 'slug' => 'manage-numbering', 'group' => 'admin', 'description' => 'Configure document number formats (admission no, receipts…).'],
            ['name' => 'Manage Academics', 'slug' => 'manage-academics', 'group' => 'academics', 'description' => 'Sessions, classes, sections, subjects, curriculum and bell schedule.'],
            ['name' => 'Manage Chart of Accounts', 'slug' => 'manage-accounts', 'group' => 'accounts', 'description' => 'Accounts, fiscal years, voucher types, payment methods, bank accounts and vendors.'],
            ['name' => 'Manage Vouchers', 'slug' => 'manage-vouchers', 'group' => 'accounts', 'description' => 'Create and edit draft journal vouchers.'],
            ['name' => 'Post Vouchers', 'slug' => 'post-vouchers', 'group' => 'accounts', 'description' => 'Post vouchers to the ledger and reverse posted vouchers.'],
            ['name' => 'View Ledger', 'slug' => 'view-ledger', 'group' => 'accounts', 'description' => 'View vouchers, account ledgers and the trial balance.'],
        ], ['slug'], ['name', 'group', 'description']);
    }
}
