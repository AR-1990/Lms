<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One area's sign-off (Accounts, Library, IT…) on a staff exit.
 */
#[Fillable(['employee_exit_id', 'area_id', 'cleared', 'cleared_by', 'cleared_at', 'remarks'])]
class ExitClearance extends Model
{
    use LogsActivity, SoftDeletes;

    protected string $activityModule = 'hr';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cleared' => 'boolean', 'cleared_at' => 'datetime'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'area_id');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
