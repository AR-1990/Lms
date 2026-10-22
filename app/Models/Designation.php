<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Database\Factories\DesignationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'department_id', 'grade_id', 'is_teaching', 'is_active'])]
class Designation extends Model
{
    /** @use HasFactory<DesignationFactory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'designations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_teaching' => 'boolean', 'is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'grade_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
