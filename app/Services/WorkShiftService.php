<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Staff shifts: start / end times and grace minutes that decide when a check-in is late.
 */
class WorkShiftService
{
    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent(WorkShift::query()->withCount('employees'))->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?WorkShift $shift, array $data): WorkShift
    {
        return DB::transaction(function () use ($shift, $data): WorkShift {
            $shift ??= new WorkShift;
            $shift->fill($data)->save();

            // One default shift: the one for staff without their own.
            if ($shift->is_default) {
                WorkShift::where('is_default', true)->whereKeyNot($shift->id)->get()->each(fn (WorkShift $other) => $other->update(['is_default' => false]));
            }

            return $shift->loadCount('employees');
        });
    }

    /**
     * @throws ValidationException while staff still follow the shift
     */
    public function delete(WorkShift $shift): void
    {
        if (Employee::where('work_shift_id', $shift->id)->exists()) {
            throw ValidationException::withMessages(['shift' => ['Staff still follow this shift. Move them to another shift first.']]);
        }

        DB::transaction(fn () => $shift->delete());
    }
}
