<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $startYear.'-'.substr((string) ($startYear + 1), -2),
            'starts_on' => "{$startYear}-08-01",
            'ends_on' => ($startYear + 1).'-07-31',
            'is_current' => false,
            'admissions_open' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => ['is_current' => true]);
    }
}
