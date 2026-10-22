<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{name: string, slug: string, group: string, description: string}>
     */
    private array $permissions = [
        ['name' => 'Manage Academics', 'slug' => 'manage-academics', 'group' => 'academics', 'description' => 'Sessions, classes, sections, subjects, curriculum and bell schedule.'],
        ['name' => 'Manage Chart of Accounts', 'slug' => 'manage-accounts', 'group' => 'accounts', 'description' => 'Accounts, fiscal years, voucher types, payment methods, bank accounts and vendors.'],
        ['name' => 'Manage Vouchers', 'slug' => 'manage-vouchers', 'group' => 'accounts', 'description' => 'Create and edit draft journal vouchers.'],
        ['name' => 'Post Vouchers', 'slug' => 'post-vouchers', 'group' => 'accounts', 'description' => 'Post vouchers to the ledger and reverse posted vouchers.'],
        ['name' => 'View Ledger', 'slug' => 'view-ledger', 'group' => 'accounts', 'description' => 'View vouchers, account ledgers and the trial balance.'],
    ];

    /**
     * Roles that receive each permission on existing databases (admin gets everything).
     *
     * @var array<string, array<int, string>>
     */
    private array $roleGrants = [
        'admin' => ['manage-academics', 'manage-accounts', 'manage-vouchers', 'post-vouchers', 'view-ledger'],
        'accounts' => ['manage-accounts', 'manage-vouchers', 'post-vouchers', 'view-ledger'],
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
