<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of a final settlement: salary for the last days, leave encashment, loan recovery or an extra.
 */
#[Fillable(['employee_exit_id', 'source', 'label', 'type', 'amount', 'sort_order'])]
class ExitSettlementLine extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
