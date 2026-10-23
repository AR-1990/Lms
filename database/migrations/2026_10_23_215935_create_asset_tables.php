<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15a (PLAN.md §15): fixed assets — categories (ledger accounts and depreciation rule), locations
 * (shared with inventory), the asset register, who holds what (assignments), repairs, physical
 * verification, monthly depreciation and disposal. Money is in paisa. No foreign-key constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('asset_categories', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('asset_account_id')->nullable()->index();
            $table->unsignedBigInteger('depreciation_account_id')->nullable()->index();
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable()->index();
            $table->string('depreciation_method', 20)->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->nullable();
            $table->decimal('salvage_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('locations', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->index();
            $table->string('building', 100)->nullable();
            $table->string('floor', 50)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('in_charge_employee_id')->nullable()->index();
            $table->boolean('is_store')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('assets', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('asset_tag', 50)->index();
            $table->unsignedBigInteger('asset_category_id')->index();
            $table->string('name');
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_no', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->date('purchase_date')->nullable()->index();
            $table->bigInteger('purchase_cost')->default(0);
            $table->bigInteger('salvage_value')->default(0);
            $table->string('invoice_no', 100)->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $table->date('warranty_expires_on')->nullable()->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->unsignedBigInteger('condition_id')->nullable()->index();
            $table->string('status', 20)->default('in_store')->index();
            $table->string('current_holder_type', 30)->nullable();
            $table->unsignedBigInteger('current_holder_id')->nullable();
            $table->index(['current_holder_type', 'current_holder_id']);
            $table->date('depreciation_start_date')->nullable();
            $table->bigInteger('current_book_value')->default(0);
            $table->string('photo_path')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $audit($table);
        });

        Schema::create('asset_assignments', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->string('assignee_type', 30);
            $table->unsignedBigInteger('assignee_id');
            $table->index(['assignee_type', 'assignee_id']);
            $table->date('assigned_on')->index();
            $table->date('expected_return_on')->nullable()->index();
            $table->date('returned_on')->nullable()->index();
            $table->unsignedBigInteger('condition_out_id')->nullable()->index();
            $table->unsignedBigInteger('condition_in_id')->nullable()->index();
            $table->unsignedBigInteger('issued_by')->nullable()->index();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->boolean('acknowledgement_signed')->default(false);
            $table->text('remarks')->nullable();
            $audit($table);
        });

        Schema::create('asset_maintenances', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->unsignedBigInteger('maintenance_type_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->date('reported_on')->index();
            $table->date('sent_on')->nullable();
            $table->date('completed_on')->nullable()->index();
            $table->bigInteger('cost')->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('reported')->index();
            $table->string('previous_status', 20)->nullable();
            $table->unsignedBigInteger('expense_id')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $audit($table);
        });

        Schema::create('asset_audits', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->date('audit_date')->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->unsignedBigInteger('conducted_by')->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $audit($table);
        });

        Schema::create('asset_audit_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_audit_id')->index();
            $table->unsignedBigInteger('asset_id')->index();
            $table->boolean('found')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable()->index();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_depreciations', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->date('period_month')->index();
            $table->bigInteger('amount');
            $table->bigInteger('book_value_after');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $audit($table);
        });

        Schema::create('asset_disposals', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('asset_id')->index();
            $table->date('disposed_on')->index();
            $table->string('method', 20)->index();
            $table->bigInteger('sale_amount')->default(0);
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->bigInteger('book_value_at_disposal')->default(0);
            $table->text('reason');
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $audit($table);
        });

        $now = now();

        foreach ([
            ['name' => 'Manage Assets', 'slug' => 'manage-assets', 'group' => 'assets', 'description' => 'Asset register, categories, locations, repairs, verification, depreciation and disposal.'],
            ['name' => 'Assign Assets', 'slug' => 'assign-assets', 'group' => 'assets', 'description' => 'Hand assets to staff, students, departments or rooms and take them back.'],
        ] as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        foreach ($roleId === null ? [] : ['manage-assets', 'assign-assets'] as $slug) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['manage-assets', 'assign-assets'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        foreach (['asset_disposals', 'asset_depreciations', 'asset_audit_items', 'asset_audits', 'asset_maintenances', 'asset_assignments', 'assets', 'locations', 'asset_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
