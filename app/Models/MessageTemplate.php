<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Text sent for a system event on one channel; `{placeholders}` are filled when it is sent.
 */
#[Fillable(['name', 'channel', 'event_key', 'subject', 'body', 'is_active'])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    public const CHANNELS = ['in_app', 'push', 'sms', 'email', 'whatsapp'];

    /**
     * Events the code raises: key => [label, placeholders].
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const EVENTS = [
        'absent_alert' => ['Student marked absent', ['student_name', 'class_section', 'date', 'school_name']],
        'result_published' => ['Exam result published', ['student_name', 'exam_name', 'school_name']],
        'challan_issued' => ['Fee challan issued', ['student_name', 'invoice_no', 'amount', 'due_date', 'school_name']],
        'payment_received' => ['Fee payment received', ['student_name', 'receipt_no', 'amount', 'date', 'school_name']],
        'fee_overdue' => ['Fee not paid by the due date', ['student_name', 'invoice_no', 'amount', 'due_date', 'school_name']],
        'diary_published' => ['Diary / homework published', ['class_section', 'date', 'entries', 'school_name']],
        'leave_decided' => ['Leave request decided', ['student_name', 'decision', 'from', 'to', 'school_name']],
        'admit_card_ready' => ['Admit card ready', ['student_name', 'exam_name', 'exam_roll_no', 'school_name']],
        'student_arrived' => ['Student arrived at school (gate scan)', ['student_name', 'time', 'date', 'school_name']],
        'fee_due_soon' => ['Fee due soon (reminder)', ['student_name', 'invoice_no', 'amount', 'due_date', 'school_name']],
        'library_overdue' => ['Library book overdue', ['borrower_name', 'book_title', 'due_date', 'school_name']],
    ];

    protected string $activityModule = 'announcements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
