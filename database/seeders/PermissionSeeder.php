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
        ], ['slug'], ['name', 'group', 'description']);
    }
}
