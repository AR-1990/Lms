<?php

namespace Database\Factories;

use App\Models\TaskBoard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskBoard>
 */
class TaskBoardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'owner_id' => User::factory(),
            'visibility' => 'private',
            'color' => fake()->hexColor(),
        ];
    }
}
