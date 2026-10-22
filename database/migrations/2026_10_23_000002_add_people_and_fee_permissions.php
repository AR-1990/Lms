<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{name: string, slug: string, group: string, description: string}>
     */
    private array $permissions = [
        ['name' => 'Manage HR', 'slug' => 'manage-hr', 'group' => 'hr', 'description' => 'Departments, designations, employees and teacher assignments.'],
        ['name' => 'Manage Recruitment', 'slug' => 'manage-recruitment', 'group' => 'hr', 'description' => 'Job openings, candidates, interviews and hiring.'],
        ['name' => 'Manage Admissions', 'slug' => 'manage-admissions', 'group' => 'admissions', 'description' => 'Inquiries, applications, tests, interviews and enrolment.'],
        ['name' => 'Manage Students', 'slug' => 'manage-students', 'group' => 'students', 'description' => 'Student records, guardians, sections and status changes.'],
        ['name' => 'Manage Fee Setup', 'slug' => 'manage-fee-setup', 'group' => 'accounts', 'description' => 'Fee heads, class fee structures, concession types and student concessions.'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $roleGrants = [
        'admin' => ['manage-hr', 'manage-recruitment', 'manage-admissions', 'manage-students', 'manage-fee-setup'],
        'accounts' => ['manage-fee-setup'],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach ($this->roleGrants as $roleSlug => $slugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

            if ($roleId === null) {
                continue;
            }

            foreach (DB::table('permissions')->whereIn('slug', $slugs)->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', array_column($this->permissions, 'slug'))->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
