<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@lms.test'],
            ['name' => 'Super Administrator', 'username' => 'admin', 'password' => 'password'],
        );

        $teacher = User::updateOrCreate(
            ['email' => 'teacher@lms.test'],
            ['name' => 'Prof. Sarah Jenkins', 'username' => 'FAC-BIO-441', 'password' => 'password'],
        );

        $student = User::updateOrCreate(
            ['email' => 'student@lms.test'],
            ['name' => 'John Doe', 'username' => 'PIA-2026-8812', 'password' => 'password'],
        );

        $parent = User::updateOrCreate(
            ['email' => 'parent@lms.test'],
            ['name' => 'Farhan Malik', 'phone' => '03001234567', 'password' => 'password'],
        );

        $accounts = User::updateOrCreate(
            ['email' => 'accounts@lms.test'],
            ['name' => 'Nadia Hussain', 'username' => 'bursar', 'password' => 'password'],
        );

        $admin->syncRoles(['admin']);
        $teacher->syncRoles(['teacher']);
        $student->syncRoles(['student']);
        $parent->syncRoles(['parent']);
        $accounts->syncRoles(['accounts']);
    }
}
