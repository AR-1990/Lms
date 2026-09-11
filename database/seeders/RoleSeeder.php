<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full access to ERP system, configuration, and users.',
                'dashboard_view' => 'dashboards.portal',
                'is_system' => true,
            ],
        );

        $teacherRole = Role::updateOrCreate(
            ['slug' => 'teacher'],
            [
                'name' => 'Teacher',
                'description' => 'Access to classes, attendance, and grading.',
                'dashboard_view' => 'dashboards.portal',
                'is_system' => true,
            ],
        );

        $studentRole = Role::updateOrCreate(
            ['slug' => 'student'],
            [
                'name' => 'Student',
                'description' => 'Access to academic and self-service features.',
                'dashboard_view' => 'dashboards.portal',
                'is_system' => true,
            ],
        );

        $parentRole = Role::updateOrCreate(
            ['slug' => 'parent'],
            [
                'name' => 'Parent',
                'description' => 'Access to children, fees, and notices.',
                'dashboard_view' => 'dashboards.portal',
                'is_system' => true,
            ],
        );

        $accountsRole = Role::updateOrCreate(
            ['slug' => 'accounts'],
            [
                'name' => 'Accounts',
                'description' => 'Access to collections, challans, and payroll.',
                'dashboard_view' => 'dashboards.portal',
                'is_system' => true,
            ],
        );

        $adminRole->syncPermissions(Permission::query()->pluck('slug')->all());
        $teacherRole->syncPermissions(['manage-classes', 'record-attendance', 'submit-grades', 'view-courses', 'view-attendance']);
        $studentRole->syncPermissions(['view-courses', 'view-attendance', 'view-grades']);
        $parentRole->syncPermissions(['view-children', 'view-fees', 'view-attendance', 'view-grades', 'view-notices']);
        $accountsRole->syncPermissions(['manage-fees', 'view-payroll', 'view-fees']);
    }
}
