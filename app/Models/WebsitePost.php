<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * News & notices on the public site. `show_in_ticker` puts the title in the home-page notice strip.
 */
#[Fillable(['category_id', 'title', 'slug', 'excerpt', 'body', 'image_path', 'published_on', 'read_minutes', 'is_featured', 'show_in_ticker', 'is_published'])]
class WebsitePost extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'website';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_on' => 'date', 'is_featured' => 'boolean', 'show_in_ticker' => 'boolean', 'is_published' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    /**
     * Seeded images live in public/images; uploaded ones on the public disk.
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return str_starts_with($this->image_path, 'images/') ? asset($this->image_path) : Storage::disk('public')->url($this->image_path);
    }
}
