<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 (PLAN.md §17.2, §21, §22): sticky notes on every dashboard, task boards with Kanban
 * columns, and real-time employee chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ---------- Sticky notes ---------- */

        Schema::create('sticky_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();
            $table->unsignedBigInteger('color_id')->nullable()->index();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('show_on_dashboard')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('remind_at')->nullable()->index();
            $table->dateTime('reminded_at')->nullable();
            $table->boolean('is_done')->default(false)->index();
            $table->dateTime('done_at')->nullable();
            $table->dateTime('archived_at')->nullable()->index();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('sticky_note_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sticky_note_id')->index();
            $table->string('text');
            $table->boolean('is_done')->default(false);
            $table->dateTime('done_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        /* ---------- Tasks ---------- */

        Schema::create('task_boards', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('visibility', 12)->default('private')->index();
            $table->string('color', 7)->nullable();
            $table->boolean('is_archived')->default(false)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_board_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_board_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 10)->default('editor');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_statuses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_board_id')->nullable()->index();
            $table->string('name', 60);
            $table->string('color', 7)->nullable();
            $table->smallInteger('sort_order')->default(0)->index();
            $table->boolean('is_done')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('task_no', 30)->index();
            $table->unsignedBigInteger('task_board_id')->nullable()->index();
            $table->unsignedBigInteger('parent_task_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('task_status_id')->index();
            $table->unsignedBigInteger('priority_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable()->index();
            $table->unsignedBigInteger('completed_by')->nullable()->index();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('spent_minutes')->nullable();
            $table->string('recurrence', 10)->default('none');
            $table->date('recurrence_until')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('task_assignees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('assigned_by')->nullable()->index();
            $table->dateTime('assigned_at');
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_watchers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedBigInteger('done_by')->nullable()->index();
            $table->dateTime('done_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->dateTime('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_comment_mentions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_comment_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
        });

        Schema::create('task_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->dateTime('remind_at')->index();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        /* ---------- Employee chat ---------- */

        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 10)->index();
            $table->string('name')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('avatar_path')->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->boolean('only_admins_can_post')->default(false);
            $table->unsignedBigInteger('last_message_id')->nullable()->index();
            $table->dateTime('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chat_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 10)->default('member');
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->dateTime('muted_until')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_id')->index();
            $table->string('type', 10)->default('text');
            $table->text('body')->nullable();
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->index();
            $table->unsignedBigInteger('forwarded_from_message_id')->nullable()->index();
            $table->uuid('client_uuid')->nullable()->index();
            $table->dateTime('edited_at')->nullable();
            $table->dateTime('deleted_for_everyone_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('chat_message_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('chat_message_deletions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('chat_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('chat_mentions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
        });

        $permissions = [
            ['name' => 'Use Tasks', 'slug' => 'use-tasks', 'group' => 'communication', 'description' => 'Task boards, My Tasks, assigning and commenting on tasks.'],
            ['name' => 'Use Staff Chat', 'slug' => 'use-chat', 'group' => 'communication', 'description' => 'One-to-one and group chat between employees.'],
        ];

        foreach ($permissions as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach (['admin', 'teacher', 'accounts'] as $roleSlug) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

            foreach ($roleId === null ? [] : ['use-tasks', 'use-chat'] as $slug) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['use-tasks', 'use-chat'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        foreach ([
            'chat_mentions', 'chat_message_reactions', 'chat_message_deletions', 'chat_message_receipts', 'chat_messages', 'chat_participants', 'chat_conversations',
            'task_reminders', 'task_comment_mentions', 'task_comments', 'task_checklist_items', 'task_watchers', 'task_assignees', 'tasks', 'task_statuses',
            'task_board_members', 'task_boards', 'sticky_note_items', 'sticky_notes',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
