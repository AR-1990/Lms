<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'payment_method_id' => fn () => PaymentMethod::where('is_online', true)->value('id'),
            'amount' => fake()->numberBetween(1000, 20000) * 100,
            'paid_on' => now()->toDateString(),
            'reference_no' => strtoupper(fake()->bothify('TX########')),
            'status' => 'pending',
        ];
    }
}
