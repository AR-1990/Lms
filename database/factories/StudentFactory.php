<?php

namespace Database\Factories;

use App\Models\Lookup;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_no' => 'PIA-T-'.fake()->unique()->numerify('#####'),
            'admission_date' => fake()->dateTimeBetween('-4 years', '-1 month')->format('Y-m-d'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender_id' => fn (): int => Lookup::where('type', 'gender')->value('id')
                ?? Lookup::factory()->create(['type' => 'gender', 'name' => 'Male'])->id,
            'date_of_birth' => fake()->dateTimeBetween('-16 years', '-4 years')->format('Y-m-d'),
            'city' => fake()->city(),
            'current_status' => 'active',
        ];
    }
}
