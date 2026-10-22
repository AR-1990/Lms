<?php

namespace Database\Factories;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Gender and employment type come from the seeded lookups (created on the fly when missing).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-T'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender_id' => fn (): int => $this->lookupId('gender', 'Male'),
            'phone' => fake()->numerify('03#########'),
            'email' => fake()->unique()->safeEmail(),
            'designation_id' => Designation::factory(),
            'employment_type_id' => fn (): int => $this->lookupId('employment_type', 'Permanent'),
            'joining_date' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'is_teaching_staff' => false,
            'status' => 'active',
        ];
    }

    public function teacher(): static
    {
        return $this->state(fn (): array => ['is_teaching_staff' => true, 'designation_id' => Designation::factory()->teaching()]);
    }

    private function lookupId(string $type, string $name): int
    {
        return Lookup::where('type', $type)->value('id')
            ?? Lookup::factory()->create(['type' => $type, 'name' => $name])->id;
    }
}
