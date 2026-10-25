<?php

namespace Database\Factories;

use App\Models\OnlineTest;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineTest>
 */
class OnlineTestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'duration_minutes' => 20,
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay()]);
    }
}
