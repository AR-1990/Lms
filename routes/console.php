<?php

use App\Models\AcademicSession;
use App\Models\ExamTerm;
use App\Models\UserDevice;
use App\Services\AdminService;
use App\Services\AdmissionService;
use App\Services\AnnouncementService;
use App\Services\AttendanceService;
use App\Services\BackupService;
use App\Services\ChatService;
use App\Services\CounsellingService;
use App\Services\FeeAutomationService;
use App\Services\LibraryService;
use App\Services\OnlineTestService;
use App\Services\PerformanceService;
use App\Services\StickyNoteService;
use App\Services\TaskService;
use App\Services\TransportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\PersonalAccessToken;

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
Schedule::call(fn () => app(CounsellingService::class)->sendFollowUpReminders())->name('counselling:follow-up-reminders')->dailyAt('07:30');
Schedule::command('chat:sync-departments')->dailyAt('01:30');

// Fees: late fines on overdue challans after midnight; the month's challans on the day set under Fees → Late Fees & Automation.
Schedule::call(fn () => app(FeeAutomationService::class)->applyLateFees())->name('fees:late-fines')->dailyAt('00:30');
Schedule::call(fn () => app(FeeAutomationService::class)->autoGenerate())->name('fees:auto-challans')->dailyAt('06:00');
// Hosting without a supervisor: the one cron entry (schedule:run) also works through the database queue
// (emails, broadcasts) every minute. Where a queue:work process already runs, this finds nothing to do.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->name('queue:drain')->everyMinute()->withoutOverlapping();

// Nightly database backup (newest 14 kept); "php artisan backup:run" makes one now.
Artisan::command('backup:run', function (): void {
    $this->info('Backup '.app(BackupService::class)->run()['file'].' created.');
})->purpose('Back up the database to storage/app/private/backups');
// Gate-scanning schools: students with no mark by the chosen time are made absent (once, then only newcomers).
Schedule::call(fn () => app(AttendanceService::class)->autoAbsent())->name('attendance:auto-absent')->everyFiveMinutes()->withoutOverlapping();

// Online tests left open (connection lost, tab closed) are marked from their saved answers once time is up.
Schedule::call(fn () => app(OnlineTestService::class)->finishExpired())->name('online-tests:finish-expired')->everyFiveMinutes();

Schedule::command('backup:run')->name('backup:nightly')->dailyAt('02:30')->withoutOverlapping();

// Admins and accounts staff get a morning reminder of students who have not paid by the due date.
// Morning summary for the principal / owner (Monday to Saturday, after the first periods are marked).
Schedule::call(fn () => app(AdminService::class)->sendDailySummary())->name('daily-summary')->days([1, 2, 3, 4, 5, 6])->at('10:30');

Schedule::call(fn () => app(LibraryService::class)->overdueReminders())->name('library:overdue-reminders')->dailyAt('08:30');
Schedule::call(fn () => app(AdmissionService::class)->followUpReminders())->name('admissions:follow-up-reminders')->dailyAt('08:45');
Schedule::call(fn () => app(FeeAutomationService::class)->dueSoonReminders())->name('fees:due-soon-reminders')->dailyAt('09:15');
Schedule::call(fn () => app(FeeAutomationService::class)->overdueAlert())->name('fees:overdue-alerts')->dailyAt('09:00');

// Mobile tokens unused for SANCTUM_IDLE_DAYS no longer work (AppServiceProvider); remove them and their devices.
Schedule::call(function (): void {
    $stale = PersonalAccessToken::whereRaw('coalesce(last_used_at, created_at) < ?', [now()->subDays((int) config('sanctum.idle_days', 60))])->pluck('id');
    UserDevice::whereIn('personal_access_token_id', $stale)->update(['is_active' => false]);
    PersonalAccessToken::whereIn('id', $stale)->delete();
    DB::table('password_reset_tokens')->where('created_at', '<', now()->subDay())->delete();
})->name('auth:prune-idle-tokens')->dailyAt('03:00');
