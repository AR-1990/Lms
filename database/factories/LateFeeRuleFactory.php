<?php

namespace Database\Factories;

use App\Models\FeeHead;
use App\Models\LateFeeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LateFeeRule>
 */
class LateFeeRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Late payment fine',
            'grace_days' => 5,
            'calculation' => 'fixed',
            'amount' => 50000,
            'fee_head_id' => fn (): ?int => FeeHead::where('code', 'LPF')->value('id'),
            'invoice_type' => 'monthly',
            'is_active' => true,
        ];
    }
}
