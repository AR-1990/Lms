<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One charge of a fee structure. Logged through its structure. Recurring charges list their months
 * in fee_structure_item_months (no months = every month).
 */
#[Fillable(['fee_structure_id', 'fee_head_id', 'charge_on', 'amount', 'due_day', 'due_in_days', 'is_mandatory', 'applies_to_new_students_only', 'sort_order'])]
class FeeStructureItem extends Model
{
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    public const CHARGE_ON = ['on_application', 'on_admission', 'recurring', 'per_exam', 'on_demand'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_day' => 'integer',
            'due_in_days' => 'integer',
            'is_mandatory' => 'boolean',
            'applies_to_new_students_only' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function months(): HasMany
    {
        return $this->hasMany(FeeStructureItemMonth::class);
    }

    public function appliesToMonth(int $month): bool
    {
        $months = $this->months->pluck('month');

        return $months->isEmpty() || $months->contains($month);
    }
}
