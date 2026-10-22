<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * A postable (non-group) asset account by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_type_id' => fn (): int => AccountType::firstOrCreate(['name' => 'Asset'], ['normal_balance' => 'debit'])->id,
            'code' => (string) fake()->unique()->numberBetween(70000, 99999),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'is_group' => false,
            'is_active' => true,
            'opening_balance' => 0,
        ];
    }

    public function group(): static
    {
        return $this->state(fn (): array => ['is_group' => true]);
    }

    public function ofType(string $typeName): static
    {
        return $this->state(fn (): array => ['account_type_id' => AccountType::where('name', $typeName)->value('id')]);
    }
}
