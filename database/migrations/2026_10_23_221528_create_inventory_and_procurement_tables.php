<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15b (PLAN.md §16): consumable and sellable stock (items, stock per store, the stock ledger),
 * purchase orders and goods receipts (GRN), issuing stock to people / departments / rooms (or selling
 * to students on a fee challan), returns, transfers between stores and stock counts. Requisitions are the
 * existing HR purchase requests. Quantities are decimal(12,3); money is in paisa. No foreign-key constraints.
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

        Schema::create('item_categories', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('inventory_account_id')->nullable()->index();
            $table->unsignedBigInteger('expense_account_id')->nullable()->index();
            $table->unsignedBigInteger('income_account_id')->nullable()->index();
            $table->unsignedBigInteger('sale_fee_head_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('inventory_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('sku', 50)->index();
            $table->unsignedBigInteger('item_category_id')->index();
            $table->string('name');
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->string('brand', 100)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->text('description')->nullable();
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('reorder_quantity', 12, 3)->default(0);
            $table->bigInteger('standard_cost')->default(0);
            $table->bigInteger('sale_price')->default(0);
            $table->boolean('is_sellable')->default(false)->index();
            $table->boolean('is_returnable')->default(false);
            $table->boolean('track_batches')->default(false);
            $table->string('barcode', 100)->nullable()->index();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->bigInteger('average_cost')->default(0);
            $table->timestamps();
            $table->unique(['inventory_item_id', 'location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->string('movement_type', 20)->index();
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost')->default(0);
            $table->decimal('balance_after', 12, 3)->default(0);
            $table->string('batch_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            $table->date('moved_on')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('po_no', 50)->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('purchase_request_id')->nullable()->index();
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->text('terms')->nullable();
            $table->text('cancel_reason')->nullable();
            $audit($table);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->index();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->unsignedBigInteger('asset_category_id')->nullable()->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost');
            $table->bigInteger('line_total');
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $audit($table);
        });

        Schema::create('goods_receipts', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('grn_no', 50)->index();
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->date('received_on')->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->string('vendor_invoice_no', 100)->nullable();
            $table->date('vendor_invoice_date')->nullable();
            $table->bigInteger('total')->default(0);
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $audit($table);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id')->index();
            $table->unsignedBigInteger('purchase_order_item_id')->nullable()->index();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->unsignedBigInteger('asset_category_id')->nullable()->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost');
            $table->bigInteger('line_total');
            $table->string('batch_no', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $audit($table);
        });

        Schema::create('stock_issues', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('issue_no', 50)->index();
            $table->date('issued_on')->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->string('recipient_type', 30);
            $table->unsignedBigInteger('recipient_id');
            $table->index(['recipient_type', 'recipient_id']);
            $table->string('purpose')->nullable();
            $table->unsignedBigInteger('purchase_request_id')->nullable()->index();
            $table->unsignedBigInteger('issued_by')->nullable()->index();
            $table->boolean('received_signature')->default(false);
            $table->boolean('is_sale')->default(false)->index();
            $table->unsignedBigInteger('fee_invoice_id')->nullable()->index();
            $table->bigInteger('total_cost')->default(0);
            $table->bigInteger('sale_total')->default(0);
            $table->string('status', 20)->default('issued')->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $audit($table);
        });

        Schema::create('stock_issue_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('stock_issue_id')->index();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost')->default(0);
            $table->bigInteger('sale_price')->default(0);
            $table->decimal('returned_quantity', 12, 3)->default(0);
            $table->date('due_back_on')->nullable()->index();
            $audit($table);
        });

        Schema::create('stock_returns', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('stock_issue_id')->index();
            $table->date('returned_on')->index();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->unsignedBigInteger('condition_id')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $audit($table);
        });

        Schema::create('stock_return_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('stock_return_id')->index();
            $table->unsignedBigInteger('stock_issue_item_id')->index();
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('condition_id')->nullable()->index();
            $audit($table);
        });

        Schema::create('stock_transfers', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('transfer_no', 50)->index();
            $table->unsignedBigInteger('from_location_id')->index();
            $table->unsignedBigInteger('to_location_id')->index();
            $table->date('transferred_on')->index();
            $table->string('status', 20)->default('completed')->index();
            $table->text('remarks')->nullable();
            $audit($table);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('stock_transfer_id')->index();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost')->default(0);
            $audit($table);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('adjustment_no', 50)->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->date('adjusted_on')->index();
            $table->unsignedBigInteger('reason_id')->nullable()->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $audit($table);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('stock_adjustment_id')->index();
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->decimal('system_qty', 12, 3);
            $table->decimal('counted_qty', 12, 3);
            $table->decimal('difference', 12, 3);
            $table->bigInteger('unit_cost')->default(0);
            $audit($table);
        });

        $now = now();

        foreach ([
            ['name' => 'Manage Inventory', 'slug' => 'manage-inventory', 'group' => 'inventory', 'description' => 'Items, stock, purchase orders, goods receipts, transfers and stock counts.'],
            ['name' => 'Issue Stock', 'slug' => 'issue-stock', 'group' => 'inventory', 'description' => 'Issue stock to staff, students, departments or rooms, sell to students and take returns.'],
        ] as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        foreach ($roleId === null ? [] : ['manage-inventory', 'issue-stock'] as $slug) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['manage-inventory', 'issue-stock'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        foreach (['stock_adjustment_items', 'stock_adjustments', 'stock_transfer_items', 'stock_transfers', 'stock_return_items', 'stock_returns', 'stock_issue_items', 'stock_issues',
            'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'stock_movements', 'stock_levels', 'inventory_items', 'item_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
