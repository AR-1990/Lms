<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class NumberSequenceService
{
    /**
     * Issue the next number of a sequence, e.g. next('admission_no') → "PIA-2026-00042".
     * The row is locked while incrementing, so two requests never receive the same number.
     * Counter bumps are saved quietly: issuing a number is not a settings change.
     */
    public function next(string $type): string
    {
        return DB::transaction(function () use ($type): string {
            $sequence = NumberSequence::query()->where('type', $type)->lockForUpdate()->firstOrFail();
            $year = (int) now()->format('Y');

            if ($sequence->reset_yearly && $sequence->current_year !== null && $sequence->current_year !== $year) {
                $sequence->next_number = 1;
                $sequence->current_year = $year;
            }

            $number = $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->current_year ??= $year;
            $sequence->saveQuietly();

            return $sequence->format($number, $year);
        });
    }

    public function preview(NumberSequence $sequence): string
    {
        $year = (int) now()->format('Y');
        $next = $sequence->reset_yearly && $sequence->current_year !== null && $sequence->current_year !== $year
            ? 1
            : $sequence->next_number;

        return $sequence->format($next, $year);
    }

    public function query(): Builder
    {
        return NumberSequence::query()->select(['id', 'type', 'label', 'prefix', 'include_year', 'padding', 'next_number', 'reset_yearly', 'current_year', 'updated_at']);
    }

    /**
     * @return Collection<int, NumberSequence>
     */
    public function all(): Collection
    {
        return $this->query()->orderBy('label')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent($this->query())
            ->escapeColumns([])
            ->addColumn('preview', fn (NumberSequence $sequence): string => $this->preview($sequence))
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(NumberSequence $sequence, array $data): NumberSequence
    {
        return DB::transaction(function () use ($sequence, $data): NumberSequence {
            $sequence->update([...$data, 'updated_by' => request()->user()?->getKey()]);

            return $sequence->refresh();
        });
    }
}
