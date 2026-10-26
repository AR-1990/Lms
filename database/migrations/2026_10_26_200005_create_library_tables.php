<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Library: the catalogue (books), each physical copy with its accession number, and every loan to a student
 * or staff member with its due date and late fine. Loan rules live on system_settings; a late fine becomes a
 * "Library Fine" challan line for students.
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

        Schema::create('library_books', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('title')->index();
            $table->string('author')->nullable()->index();
            $table->string('isbn', 20)->nullable()->index();
            $table->string('publisher')->nullable();
            $table->string('edition', 50)->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('shelf', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('library_copies', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('library_book_id')->index();
            $table->string('accession_no', 30)->unique();
            $table->string('status', 20)->default('available')->index();
            $table->string('remarks')->nullable();
            $audit($table);
        });

        Schema::create('library_issues', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('library_copy_id')->index();
            $table->string('borrower_type', 20);
            $table->unsignedBigInteger('borrower_id');
            $table->date('issued_on')->index();
            $table->date('due_on')->index();
            $table->date('returned_on')->nullable()->index();
            $table->bigInteger('fine_amount')->default(0);
            $table->unsignedBigInteger('fee_invoice_id')->nullable()->index();
            $table->string('status', 20)->default('issued')->index();
            $table->string('remarks')->nullable();
            $audit($table);
            $table->index(['borrower_type', 'borrower_id']);
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('library_loan_days')->default(14)->after('fee_reminder_days');
            $table->unsignedTinyInteger('library_max_books')->default(2)->after('library_loan_days');
            $table->bigInteger('library_fine_per_day')->default(0)->after('library_max_books');
        });

        $now = now();

        if (DB::table('number_sequences')->where('type', 'library_accession')->doesntExist()) {
            DB::table('number_sequences')->insert(['type' => 'library_accession', 'label' => 'Library accession', 'prefix' => 'ACC-', 'include_year' => false, 'padding' => 6, 'next_number' => 1, 'reset_yearly' => false, 'current_year' => (int) $now->format('Y'), 'created_at' => $now, 'updated_at' => $now]);
        }

        $income = DB::table('accounts')->where('code', '4900')->value('id');

        if ($income !== null && DB::table('fee_heads')->where('code', 'LIB')->doesntExist()) {
            DB::table('fee_heads')->insert(['name' => 'Library Fine', 'code' => 'LIB', 'income_account_id' => $income, 'is_refundable' => false, 'is_concession_allowed' => false, 'sort_order' => 120, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['Fiction', 'Reference', 'Textbook', 'Science', 'History', 'Islamic Studies', 'Urdu Literature', 'Children'] as $index => $name) {
            if (DB::table('lookups')->where('type', 'book_category')->where('name', $name)->doesntExist()) {
                DB::table('lookups')->insert(['type' => 'book_category', 'name' => $name, 'sort_order' => ($index + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $permissionId = DB::table('permissions')->where('slug', 'manage-library')->value('id')
            ?? DB::table('permissions')->insertGetId(['name' => 'Manage Library', 'slug' => 'manage-library', 'group' => 'library', 'description' => 'Book catalogue and copies, issuing and returning books, library fines.', 'created_at' => $now, 'updated_at' => $now]);
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null && DB::table('permission_role')->where('role_id', $adminRoleId)->where('permission_id', $permissionId)->doesntExist()) {
            DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['library_loan_days', 'library_max_books', 'library_fine_per_day']);
        });

        Schema::dropIfExists('library_issues');
        Schema::dropIfExists('library_copies');
        Schema::dropIfExists('library_books');
    }
};
