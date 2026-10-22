<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class FiscalYearService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public function query(): Builder
    {
        return FiscalYear::query()
            ->select(['id', 'name', 'starts_on', 'ends_on', 'is_current', 'status', 'closed_at', 'created_at', 'updated_at'])
            ->withCount('journalEntries');
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent($this->query())
            ->escapeColumns([])
            ->editColumn('starts_on', fn (FiscalYear $year): string => $year->starts_on->toDateString())
            ->editColumn('ends_on', fn (FiscalYear $year): string => $year->ends_on->toDateString())
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FiscalYear
    {
        return DB::transaction(function () use ($data): FiscalYear {
            $year = FiscalYear::create([...$data, 'is_current' => $data['is_current'] ?? false, 'status' => FiscalYear::STATUS_OPEN]);
            $this->keepSingleCurrent($year);

            return $year->loadCount('journalEntries');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when vouchers already sit in the year and its dates would change
     */
    public function update(FiscalYear $year, array $data): FiscalYear
    {
        $datesChange = $year->starts_on->toDateString() !== $data['starts_on'] || $year->ends_on->toDateString() !== $data['ends_on'];

        if ($datesChange && $year->journalEntries()->exists()) {
            throw ValidationException::withMessages(['starts_on' => ['Vouchers are already dated in this year, so its dates cannot change.']]);
        }

        return DB::transaction(function () use ($year, $data): FiscalYear {
            $year->update($data);
            $this->keepSingleCurrent($year);

            return $year->loadCount('journalEntries');
        });
    }

    /**
     * @throws ValidationException when the year already has vouchers
     */
    public function delete(FiscalYear $year): void
    {
        if ($year->journalEntries()->exists()) {
            throw ValidationException::withMessages(['fiscal_year' => ['This year has vouchers and cannot be deleted. Close it instead.']]);
        }

        DB::transaction(fn () => $year->delete());
    }

    /**
     * Open or close a year. A closed year accepts no new, edited or posted vouchers.
     */
    public function setStatus(FiscalYear $year, string $status): FiscalYear
    {
        if ($year->status === $status) {
            throw ValidationException::withMessages(['status' => ["{$year->name} is already {$status}."]]);
        }

        return DB::transaction(function () use ($year, $status): FiscalYear {
            $old = $year->status;
            $year->forceFill([
                'status' => $status,
                'closed_by' => $status === FiscalYear::STATUS_CLOSED ? auth()->id() : null,
                'closed_at' => $status === FiscalYear::STATUS_CLOSED ? now() : null,
            ])->saveQuietly();

            $this->activityLogger->record(
                'fiscal_years',
                $status === FiscalYear::STATUS_CLOSED ? 'closed' : 'reopened',
                (auth()->user()?->name ?? 'System').' '.($status === FiscalYear::STATUS_CLOSED ? 'closed' : 'reopened')." {$year->name}",
                $year,
                ['status' => ['old' => $old, 'new' => $status]],
            );

            return $year->loadCount('journalEntries');
        });
    }

    private function keepSingleCurrent(FiscalYear $year): void
    {
        if (! $year->is_current) {
            return;
        }

        FiscalYear::where('is_current', true)->whereKeyNot($year->id)->get()
            ->each(fn (FiscalYear $other) => $other->update(['is_current' => false]));
    }
}
