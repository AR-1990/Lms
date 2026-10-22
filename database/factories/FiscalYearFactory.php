<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2020);

        return [
            'name' => 'FY '.$startYear.'-'.substr((string) ($startYear + 1), -2),
            'starts_on' => "{$startYear}-07-01",
            'ends_on' => ($startYear + 1).'-06-30',
            'is_current' => false,
            'status' => FiscalYear::STATUS_OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => FiscalYear::STATUS_CLOSED, 'closed_at' => now()]);
    }
}
