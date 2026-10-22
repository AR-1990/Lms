<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'name' => strtoupper(fake()->unique()->lexify('??')),
            'room' => 'Room '.fake()->numberBetween(1, 40),
            'capacity' => 30,
        ];
    }
}
