<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Communication (PLAN.md §17.1 and §17.3): announcements with audiences and read receipts, the
 * in-app notification bell, message templates per event and the delivery log of every SMS /
 * email / push sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('color', 7)->nullable();
            $table->string('icon', 30)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            $table->string('cover_image_path')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_urgent')->default(false);
            $table->dateTime('publish_at')->nullable()->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->boolean('send_push')->default(true);
            $table->boolean('send_sms')->default(false);
            $table->boolean('send_email')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('published_by')->nullable()->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcement_audiences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_id')->index();
            $table->string('audience_type', 20)->index();
            $table->unsignedBigInteger('audience_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->dateTime('read_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('device', 10)->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('event_key', 40)->index();
            $table->string('title');
            $table->string('body', 1000)->nullable();
            $table->string('url')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->dateTime('read_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('channel', 10)->index();
            $table->string('event_key', 40)->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 10)->index();
            $table->string('recipient');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('message_template_id')->nullable()->index();
            $table->string('event_key', 40)->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 12)->default('queued')->index();
            $table->string('provider_reference')->nullable();
            $table->string('error', 500)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });

        $permissions = [
            ['name' => 'Manage Announcements', 'slug' => 'manage-announcements', 'group' => 'communication', 'description' => 'Write, schedule and publish announcements; manage their categories, message templates and the delivery log.'],
        ];

        foreach ($permissions as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-announcements')->value('id')],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-announcements')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        foreach (['notification_logs', 'message_templates', 'user_notifications', 'announcement_reads', 'announcement_audiences', 'announcements', 'announcement_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
