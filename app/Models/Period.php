<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\PeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One slot of the daily bell schedule.
 */
#[Fillable(['campus_id', 'name', 'starts_at', 'ends_at', 'is_break', 'sort_order'])]
class Period extends Model
{
    /** @use HasFactory<PeriodFactory> */
    use HasFactory, LogsActivity, TracksAuthor;

    protected string $activityModule = 'periods';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
