<?php

namespace Database\Factories;

use App\Models\AppraisalCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppraisalCycle>
 */
class AppraisalCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Annual Review '.fake()->unique()->numberBetween(2020, 2099),
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'self_review' => true,
            'status' => 'draft',
        ];
    }
}
