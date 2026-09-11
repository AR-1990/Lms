<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Core Roles
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full access to ERP system, user management, and configuration.',
                'is_system' => true,
            ]
        );

        $teacherRole = Role::firstOrCreate(
            ['slug' => 'teacher'],
            [
                'name' => 'Teacher',
                'description' => 'Access to assigned classes, attendance recording, and grading.',
                'is_system' => true,
            ]
        );

        $studentRole = Role::firstOrCreate(
            ['slug' => 'student'],
            [
                'name' => 'Student',
                'description' => 'Access to enrolled courses, attendance records, and personal grades.',
                'is_system' => true,
            ]
        );

        $parentRole = Role::firstOrCreate(
            ['slug' => 'parent'],
            [
                'name' => 'Parent',
                'description' => 'Access to children attendance, fee status, and academic notices.',
                'is_system' => true,
            ]
        );

        $accountsRole = Role::firstOrCreate(
            ['slug' => 'accounts'],
            [
                'name' => 'Accounts',
                'description' => 'Access to fee collections, challans, and payroll overview.',
                'is_system' => true,
            ]
        );

        // 2. Standard Permissions
        $permissions = [
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'group' => 'users'],
            ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'group' => 'roles'],
            ['name' => 'View System Stats', 'slug' => 'view-stats', 'group' => 'admin'],
            ['name' => 'Manage Classes', 'slug' => 'manage-classes', 'group' => 'academics'],
            ['name' => 'Record Attendance', 'slug' => 'record-attendance', 'group' => 'academics'],
            ['name' => 'Submit Grades', 'slug' => 'submit-grades', 'group' => 'academics'],
            ['name' => 'View Enrolled Courses', 'slug' => 'view-courses', 'group' => 'student'],
            ['name' => 'View Attendance', 'slug' => 'view-attendance', 'group' => 'student'],
            ['name' => 'View Grades', 'slug' => 'view-grades', 'group' => 'student'],
            ['name' => 'View Children', 'slug' => 'view-children', 'group' => 'parent'],
            ['name' => 'View Fee Status', 'slug' => 'view-fees', 'group' => 'parent'],
            ['name' => 'View Notices', 'slug' => 'view-notices', 'group' => 'parent'],
            ['name' => 'Manage Fee Collections', 'slug' => 'manage-fees', 'group' => 'accounts'],
            ['name' => 'View Payroll', 'slug' => 'view-payroll', 'group' => 'accounts'],
        ];

        Permission::upsert(
            $permissions,
            ['slug'],
            ['name', 'group']
        );

        // 3. Assign Permissions to Roles
        $allPermissions = Permission::all();
        $adminRole->syncPermissions($allPermissions->pluck('id')->toArray());

        $teacherRole->syncPermissions(
            Permission::whereIn('slug', [
                'manage-classes', 'record-attendance', 'submit-grades', 'view-courses', 'view-attendance',
            ])->pluck('id')->toArray()
        );

        $studentRole->syncPermissions(
            Permission::whereIn('slug', [
                'view-courses', 'view-attendance', 'view-grades',
            ])->pluck('id')->toArray()
        );

        $parentRole->syncPermissions(
            Permission::whereIn('slug', [
                'view-children', 'view-fees', 'view-attendance', 'view-grades', 'view-notices',
            ])->pluck('id')->toArray()
        );

        $accountsRole->syncPermissions(
            Permission::whereIn('slug', [
                'manage-fees', 'view-payroll', 'view-fees',
            ])->pluck('id')->toArray()
        );

        // 4. Create Demo Users for Testing (password: password)
        // Plain password — User model hashed cast handles hashing.
        $admin = User::firstOrCreate(
            ['email' => 'admin@lms.test'],
            [
                'name' => 'Super Administrator',
                'password' => 'password',
            ]
        );
        $admin->syncRoles(['admin']);

        $teacher = User::firstOrCreate(
            ['email' => 'teacher@lms.test'],
            [
                'name' => 'Prof. Sarah Jenkins',
                'password' => 'password',
            ]
        );
        $teacher->syncRoles(['teacher']);

        $student = User::firstOrCreate(
            ['email' => 'student@lms.test'],
            [
                'name' => 'John Doe',
                'password' => 'password',
            ]
        );
        $student->syncRoles(['student']);

        $parent = User::firstOrCreate(
            ['email' => 'parent@lms.test'],
            [
                'name' => 'Farhan Malik',
                'password' => 'password',
            ]
        );
        $parent->syncRoles(['parent']);

        $accounts = User::firstOrCreate(
            ['email' => 'accounts@lms.test'],
            [
                'name' => 'Nadia Hussain',
                'password' => 'password',
            ]
        );
        $accounts->syncRoles(['accounts']);
    }
}
