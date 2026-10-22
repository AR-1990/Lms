<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Holidays (no attendance), exams, meetings and events; public ones also show on the website.
 */
#[Fillable(['campus_id', 'title', 'event_type_id', 'starts_on', 'ends_on', 'start_time', 'end_time', 'venue', 'is_holiday', 'is_public', 'audience_role_id', 'description'])]
class CalendarEvent extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'calendar';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_holiday' => 'boolean', 'is_public' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return $this->title;
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'event_type_id');
    }
}
