<?php

namespace Database\Factories;

use App\Models\LibraryBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryBook>
 */
class LibraryBookFactory extends Factory
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
            'author' => fake()->name(),
            'isbn' => fake()->isbn13(),
            'shelf' => 'A-'.fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
