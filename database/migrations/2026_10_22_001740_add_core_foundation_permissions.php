<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{name: string, slug: string, group: string, description: string}>
     */
    private array $permissions = [
        ['name' => 'Manage Lookups', 'slug' => 'manage-lookups', 'group' => 'admin', 'description' => 'Maintain dropdown master data (lookups).'],
        ['name' => 'View Activity Log', 'slug' => 'view-activity-log', 'group' => 'admin', 'description' => 'See who did what, on which module.'],
    ];

    /**
     * Add the permissions to existing databases and give them to the admin role.
     */
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        foreach ($this->permissions as $permission) {
            $permissionId = DB::table('permissions')->where('slug', $permission['slug'])->value('id')
                ?? DB::table('permissions')->insertGetId([...$permission, 'created_at' => now(), 'updated_at' => now()]);

            $alreadyAttached = DB::table('permission_role')
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if ($adminRoleId !== null && ! $alreadyAttached) {
                DB::table('permission_role')->insert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_column($this->permissions, 'slug'))
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
