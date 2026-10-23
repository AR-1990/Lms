<?php

namespace Database\Factories;

use App\Models\StickyNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StickyNote>
 */
class StickyNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'body' => fake()->sentence(10),
            'sort_order' => 0,
        ];
    }
}
