<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17d (PLAN.md §20): counselling sessions with a student — concern, notes, action plan, follow-up date
 * (with reminders) and whether parents were met. Confidential notes are never shown to parents or students.
 * A session may come from a performance alert. No foreign-key constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselling_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->date('session_date')->index();
            $table->unsignedBigInteger('counsellor_id')->index();
            $table->unsignedBigInteger('concern_id')->nullable()->index();
            $table->unsignedBigInteger('performance_alert_id')->nullable()->index();
            $table->text('notes');
            $table->text('action_plan')->nullable();
            $table->date('follow_up_on')->nullable()->index();
            $table->timestamp('follow_up_done_at')->nullable();
            $table->boolean('parents_met')->default(false);
            $table->boolean('is_confidential')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        if (! DB::table('permissions')->where('slug', 'manage-counselling')->exists()) {
            DB::table('permissions')->insert(['name' => 'Manage Counselling', 'slug' => 'manage-counselling', 'group' => 'academics', 'description' => 'Record counselling sessions with students, follow-ups and confidential notes.', 'created_at' => $now, 'updated_at' => $now]);
        }

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($roleId !== null) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-counselling')->value('id')], ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-counselling')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        Schema::dropIfExists('counselling_notes');
    }
};
