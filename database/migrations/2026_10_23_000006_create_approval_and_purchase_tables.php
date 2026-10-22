<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin approval for deletions and purchases, and HR purchase requests.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{name: string, slug: string, group: string, description: string}>
     */
    private array $permissions = [
        ['name' => 'Approve Requests', 'slug' => 'approve-requests', 'group' => 'administration', 'description' => 'Approve or reject deletions and purchase requests. Deletions by these users need no approval.'],
        ['name' => 'Manage Purchase Requests', 'slug' => 'manage-purchases', 'group' => 'hr', 'description' => 'Raise purchase requests that go to an administrator for approval.'],
    ];

    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('request_type', 20)->index();
            $table->string('module', 50)->index();
            $table->string('subject_type', 150);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();
            $table->text('reason')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->timestamp('requested_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('request_no', 30)->unique();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->date('request_date');
            $table->date('needed_by')->nullable();
            $table->text('purpose')->nullable();
            $table->bigInteger('estimated_total')->default(0);
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('approval_request_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id')->index();
            $table->string('item_name');
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->bigInteger('estimated_unit_price')->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->string('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ($this->permissions as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null) {
            foreach (DB::table('permissions')->whereIn('slug', array_column($this->permissions, 'slug'))->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $adminRoleId, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', array_column($this->permissions, 'slug'))->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('approval_requests');
    }
};
