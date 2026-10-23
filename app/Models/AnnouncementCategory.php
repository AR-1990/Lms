<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\AnnouncementCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Notice, Holiday, Event, Exam, Fee, Emergency… (managed by the school).
 */
#[Fillable(['name', 'color', 'icon', 'sort_order', 'is_active'])]
class AnnouncementCategory extends Model
{
    /** @use HasFactory<AnnouncementCategoryFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'announcements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'category_id');
    }
}
