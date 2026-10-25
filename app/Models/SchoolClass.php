<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A grade level (`classes` table) — "Class" is a reserved word in PHP.
 */
#[Fillable(['campus_id', 'name', 'code', 'level_order', 'wing_id', 'next_class_id', 'is_active'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use BelongsToCampus, HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected $table = 'classes';

    protected string $activityModule = 'classes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function wing(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'wing_id');
    }

    public function nextClass(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_class_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function curriculum(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }
}
