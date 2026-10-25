<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\MessageTemplate;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Announcement categories and message templates (master data, edited under Admin → Announcements),
 * plus a few demo announcements that are already in everyone's notices and bell.
 */
class CommunicationSeeder extends Seeder
{
    public function run(AnnouncementService $announcementService, NotificationService $notificationService): void
    {
        foreach ([
            ['Notice', '#1d4ed8', 'inbox'],
            ['Holiday', '#059669', 'calendar'],
            ['Event', '#7c3aed', 'sparkles'],
            ['Exam', '#b45309', 'book'],
            ['Fee', '#be123c', 'hash'],
            ['Achievement', '#ca8a04', 'heart'],
            ['Emergency', '#dc2626', 'alert'],
        ] as $position => [$name, $color, $icon]) {
            AnnouncementCategory::updateOrCreate(['name' => $name], ['color' => $color, 'icon' => $icon, 'sort_order' => $position, 'is_active' => true]);
        }

        foreach ([
            ['Absent alert (bell)', 'in_app', 'absent_alert', 'Absent today: {student_name}', '{student_name} ({class_section}) was marked absent on {date}. Please inform the class teacher if this is a mistake.'],
            ['Absent alert (SMS)', 'sms', 'absent_alert', null, '{school_name}: {student_name} ({class_section}) was absent on {date}.'],
            ['Result published (bell)', 'in_app', 'result_published', 'Result published: {exam_name}', 'The {exam_name} result of {student_name} is now available in the portal.'],
            ['Result published (email)', 'email', 'result_published', '{exam_name} result — {student_name}', "Dear parent,\n\nThe {exam_name} result of {student_name} has been published. Please sign in to the portal to view the report card.\n\n{school_name}"],
            ['Fee challan issued (bell)', 'in_app', 'challan_issued', 'New fee challan: {student_name}', 'Challan {invoice_no} of {amount} is due by {due_date}. Download it from the portal.'],
            ['Fee challan issued (SMS)', 'sms', 'challan_issued', null, '{school_name}: challan {invoice_no} for {student_name}, {amount}, due {due_date}.'],
            ['Fee payment received (bell)', 'in_app', 'payment_received', 'Payment received: {amount}', 'Thank you. Receipt {receipt_no} for {student_name} ({amount}, {date}) is in the portal.'],
            ['Fee payment received (SMS)', 'sms', 'payment_received', null, '{school_name}: received {amount} for {student_name} on {date}. Receipt {receipt_no}.'],
            ['Fee overdue (bell)', 'in_app', 'fee_overdue', 'Fee overdue: {student_name}', 'Challan {invoice_no} ({amount}) was due on {due_date} and is still unpaid. Please pay soon to avoid a late fine.'],
            ['Fee overdue (SMS)', 'sms', 'fee_overdue', null, '{school_name}: {student_name} fee {amount} (challan {invoice_no}) was due on {due_date}. Please pay to avoid a late fine.'],
            ['Diary published (bell)', 'in_app', 'diary_published', 'Diary for {date}: {class_section}', '{entries} — open the diary in the portal.'],
            ['Leave decided (bell)', 'in_app', 'leave_decided', 'Leave {decision}: {student_name}', 'The leave request for {from} to {to} was {decision}.'],
            ['Admit card ready (bell)', 'in_app', 'admit_card_ready', 'Admit card ready: {exam_name}', 'The {exam_name} admit card of {student_name} (roll no {exam_roll_no}) can be downloaded from the portal.'],
        ] as [$name, $channel, $event, $subject, $body]) {
            MessageTemplate::updateOrCreate(['name' => $name], ['channel' => $channel, 'event_key' => $event, 'subject' => $subject, 'body' => $body, 'is_active' => true]);
        }

        $category = fn (string $name): ?int => AnnouncementCategory::where('name', $name)->value('id');
        $adminId = User::where('email', 'admin@lms.test')->value('id');
        $grade5 = SchoolClass::where('name', 'Grade 5')->value('id');

        foreach ([
            ['Winter vacation from 22 December', 'Holiday', 'School stays closed from 22 December to 2 January. Classes resume on 3 January.', [['everyone', null]], ['is_public' => true, 'is_pinned' => true]],
            ['Parent-teacher meeting for Grade 5', 'Event', 'Parents of Grade 5 are invited on Saturday at 10:00 AM to discuss mid-term progress.', [['class', $grade5]], ['requires_acknowledgement' => true]],
            ['Staff meeting on Monday', 'Notice', 'All staff meet in the conference room on Monday at 2:00 PM after classes.', [['all_employees', null]], []],
            ['Mid-term date sheet released', 'Exam', 'The mid-term date sheet is now available. Admit cards will be issued a week before the first paper.', [['all_students', null], ['all_parents', null]], ['is_urgent' => true]],
        ] as $position => [$title, $categoryName, $summary, $audiences, $flags]) {
            if (Announcement::where('title', $title)->exists()) {
                continue;
            }

            $announcement = Announcement::create([
                'title' => $title, 'slug' => Str::slug($title), 'category_id' => $category($categoryName), 'summary' => $summary,
                'body' => $summary."\n\nFor any questions, please contact the school office.", 'status' => 'published',
                'publish_at' => now()->subDays(4 - $position), 'published_by' => $adminId, 'send_push' => true, ...$flags,
            ]);

            foreach ($audiences as [$type, $id]) {
                $announcement->audiences()->create(['audience_type' => $type, 'audience_id' => $id]);
            }

            $notificationService->notify($announcementService->recipientIds($announcement), 'announcement', $title, $summary, route('notices', ['announcement' => $announcement->id]), $announcement, (bool) ($flags['is_urgent'] ?? false));
        }

        if (Announcement::where('title', 'Fee reminder for November')->exists()) {
            return;
        }

        $draft = Announcement::create([
            'title' => 'Fee reminder for November', 'slug' => 'fee-reminder-for-november', 'category_id' => $category('Fee'),
            'summary' => 'November fee is due by the 10th. A late fee applies after the due date.', 'body' => 'November fee is due by the 10th. A late fee applies after the due date.',
            'status' => 'draft', 'send_push' => true, 'send_sms' => true,
        ]);
        $draft->audiences()->create(['audience_type' => 'all_parents']);
    }
}
