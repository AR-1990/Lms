<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chat oversight: the admin reads every staff chat and manages any group's members (PLAN.md §13).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('permissions')->where('slug', 'monitor-chat')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Monitor Staff Chat', 'slug' => 'monitor-chat', 'group' => 'communication',
                'description' => 'Read every staff chat without being seen, get told about new groups, and add or remove group members.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($roleId !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'monitor-chat')->value('id')],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'monitor-chat')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
