<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\LookupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One option of a dynamic dropdown list (religion, relation, bank…), grouped by `type`.
 */
#[Fillable(['type', 'name', 'code', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'])]
class Lookup extends Model
{
    /** @use HasFactory<LookupFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected string $activityModule = 'lookups';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Options of one list, in display order.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
