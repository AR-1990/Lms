<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_no' => 'TSK-'.fake()->unique()->numerify('#####'),
            'title' => ucfirst(fake()->words(4, true)),
            'task_status_id' => fn (): ?int => TaskStatus::whereNull('task_board_id')->orderBy('sort_order')->value('id'),
            'recurrence' => 'none',
        ];
    }
}
