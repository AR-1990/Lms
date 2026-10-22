<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'subject_type_id', 'has_practical', 'is_active'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'subjects';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_practical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subjectType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'subject_type_id');
    }

    public function curriculum(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }
}
