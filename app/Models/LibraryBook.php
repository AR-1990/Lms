<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\LibraryBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A title in the library catalogue; each physical book is a copy with its own accession number.
 */
#[Fillable(['campus_id', 'title', 'author', 'isbn', 'publisher', 'edition', 'category_id', 'shelf', 'description', 'is_active'])]
class LibraryBook extends Model
{
    /** @use HasFactory<LibraryBookFactory> */
    use BelongsToCampus, HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'library';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function activityLabel(): string
    {
        return 'book '.$this->title;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(LibraryCopy::class);
    }
}
