<?php

use App\Models\AcademicSession;
use App\Models\ExamTerm;
use App\Services\AnnouncementService;
use App\Services\ChatService;
use App\Services\PerformanceService;
use App\Services\StickyNoteService;
use App\Services\TaskService;
use App\Services\TransportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('performance:refresh', function (PerformanceService $performanceService) {
    $terms = ExamTerm::whereIn('academic_session_id', AcademicSession::where('is_current', true)->select('id'))->get();

    foreach ($terms as $term) {
        $this->info($term->name.': '.$performanceService->compute($term).' students');
    }
})->purpose('Rebuild performance snapshots and at-risk alerts for the current session');

Schedule::command('performance:refresh')->dailyAt('02:00');

Artisan::command('announcements:publish-due', function (AnnouncementService $announcementService) {
    [$published, $archived] = $announcementService->publishDue();
    $this->info("Published {$published}, archived {$archived}.");
})->purpose('Publish scheduled announcements that are due and archive expired ones');

Schedule::command('announcements:publish-due')->everyFiveMinutes();

Artisan::command('sticky-notes:remind', function (StickyNoteService $stickyNoteService) {
    [$reminded, $archived] = $stickyNoteService->remindDue();
    $this->info("Reminded {$reminded}, archived {$archived}.");
})->purpose('Send due sticky-note reminders and archive notes done more than 7 days ago');

Schedule::command('sticky-notes:remind')->everyMinute();

Artisan::command('tasks:remind', function (TaskService $taskService) {
    $this->info('Sent '.$taskService->remindDue().' task reminders.');
})->purpose('Send bell reminders for tasks that are due soon');

Schedule::command('tasks:remind')->everyFiveMinutes();

Artisan::command('chat:sync-departments', function (ChatService $chatService) {
    $this->info($chatService->syncDepartmentGroups().' department groups in sync.');
})->purpose('Keep one chat group per department with its current staff');

Schedule::call(fn () => app(TransportService::class)->sendExpiryAlerts())->name('transport:expiry-alerts')->dailyAt('08:00');
Schedule::command('chat:sync-departments')->dailyAt('01:30');
