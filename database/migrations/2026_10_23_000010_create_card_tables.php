<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (PLAN.md §23): designable card templates, issued student / employee ID cards
 * with a QR that opens /verify/{token}, and a log of every print.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name');
            $table->string('card_type', 20)->index();
            $table->string('orientation', 10)->default('portrait');
            $table->decimal('width_mm', 5, 1)->default(54);
            $table->decimal('height_mm', 5, 1)->default(85.6);
            $table->string('front_background_path')->nullable();
            $table->string('back_background_path')->nullable();
            $table->string('primary_color', 7)->default('#1d2b45');
            $table->string('text_color', 7)->default('#1f2937');
            $table->boolean('show_qr')->default(true);
            $table->boolean('show_barcode')->default(false);
            $table->text('back_text')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('card_template_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_template_id')->index();
            $table->string('side', 5);
            $table->string('field_key', 40);
            $table->string('custom_text')->nullable();
            $table->decimal('x_mm', 5, 1);
            $table->decimal('y_mm', 5, 1);
            $table->decimal('width_mm', 5, 1)->nullable();
            $table->decimal('height_mm', 5, 1)->nullable();
            $table->decimal('font_size', 4, 1)->default(8);
            $table->string('font_weight', 10)->default('normal');
            $table->string('color', 7)->nullable();
            $table->string('align', 10)->default('left');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('holder_type', 20);
            $table->unsignedBigInteger('holder_id');
            $table->unsignedBigInteger('card_template_id')->index();
            $table->string('card_no', 30)->unique();
            $table->string('qr_token', 64)->unique();
            $table->unsignedBigInteger('academic_session_id')->nullable()->index();
            $table->date('issued_on');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('replacement_of_id')->nullable()->index();
            $table->unsignedBigInteger('replacement_fee_invoice_id')->nullable()->index();
            $table->string('status_reason')->nullable();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['holder_type', 'holder_id']);
        });

        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40)->index();
            $table->string('printable_type', 100)->nullable();
            $table->unsignedBigInteger('printable_id')->nullable();
            $table->unsignedBigInteger('printed_by')->nullable()->index();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->boolean('is_reprint')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['printable_type', 'printable_id']);
        });

        if (! DB::table('permissions')->where('slug', 'manage-cards')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Manage ID Cards', 'slug' => 'manage-cards', 'group' => 'students',
                'description' => 'Card templates, issuing and printing student and employee ID cards.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-cards')->value('id')],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-cards')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        Schema::dropIfExists('print_logs');
        Schema::dropIfExists('id_cards');
        Schema::dropIfExists('card_template_fields');
        Schema::dropIfExists('card_templates');
    }
};
