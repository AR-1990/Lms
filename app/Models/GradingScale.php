<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'is_default'])]
class GradingScale extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'exams';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GradingScaleItem::class)->orderByDesc('min_percent');
    }

    /**
     * The grade for a percentage (items are checked from the top band down).
     */
    public function gradeFor(float $percent): ?GradingScaleItem
    {
        return $this->items->first(fn (GradingScaleItem $item): bool => $percent >= $item->min_percent);
    }
}
