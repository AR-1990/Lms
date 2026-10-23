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
