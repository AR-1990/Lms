<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A staff shift: when the day starts and ends, and the grace minutes before a check-in is late.
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'grace_minutes', 'is_default', 'is_active'])]
class WorkShift extends Model
{
    use LogsActivity, SoftDeletes, TracksAuthor;

    protected string $activityModule = 'hr';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['grace_minutes' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The shift an employee follows (theirs, else the default one).
     */
    public static function for(Employee $employee): ?self
    {
        return ($employee->work_shift_id ? self::find($employee->work_shift_id) : null) ?? self::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Minutes late for a check-in time, or 0 when within the grace minutes.
     */
    public function lateMinutes(string $checkIn): int
    {
        $start = Carbon::createFromFormat('H:i:s', strlen($this->starts_at) === 5 ? $this->starts_at.':00' : $this->starts_at);
        $in = Carbon::createFromFormat('H:i:s', strlen($checkIn) === 5 ? $checkIn.':00' : $checkIn);
        $minutes = (int) floor($start->diffInMinutes($in, false));

        return $minutes > $this->grace_minutes ? $minutes : 0;
    }
}
