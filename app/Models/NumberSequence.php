<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Format and counter for one kind of human document number (admission no, receipt no…).
 */
#[Fillable(['type', 'label', 'prefix', 'include_year', 'padding', 'next_number', 'reset_yearly', 'current_year', 'updated_by'])]
class NumberSequence extends Model
{
    use LogsActivity;

    protected string $activityModule = 'numbering';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'include_year' => 'boolean',
            'reset_yearly' => 'boolean',
            'padding' => 'integer',
            'next_number' => 'integer',
            'current_year' => 'integer',
        ];
    }

    /**
     * How a given counter value looks once formatted, e.g. PIA-2026-00042.
     */
    public function format(int $number, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return ($this->prefix ?? '')
            .($this->include_year ? $year.'-' : '')
            .str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);
    }

    public function activityLabel(): string
    {
        return $this->label;
    }
}
