<?php

namespace Database\Seeders;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ChatService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Department chat groups (kept in sync nightly) and a demo conversation between the admin and a teacher.
 */
class ChatSeeder extends Seeder
{
    public function run(ChatService $chatService): void
    {
        $chatService->syncDepartmentGroups();

        $admin = User::where('email', 'admin@lms.test')->first();
        $teacher = User::where('email', 'teacher@lms.test')->first();

        if ($admin === null || $teacher === null || ChatConversation::where('type', 'direct')->exists()) {
            return;
        }

        Auth::setUser($admin);
        ActivityLogger::quietly(function () use ($chatService, $admin, $teacher): void {
            $direct = $chatService->find($admin, $chatService->startDirect($admin, $teacher)->id);
            $chatService->send($admin, $direct, 'Assalam o Alaikum! Can you share the Grade 5 house team list by Thursday?', null, null);
            $chatService->send($teacher, $chatService->find($teacher, $direct->id), 'Walaikum Assalam, sure. I will add it to the Sports Day board today.', null, null);

            $group = $chatService->createGroup($admin, 'Staff Room', 'Day-to-day coordination for all staff.', [$teacher->id], false);
            $chatService->send($admin, $chatService->find($admin, $group->id), 'Welcome to the staff room group 🎉', null, null);
        });
        Auth::forgetUser();
    }
}
