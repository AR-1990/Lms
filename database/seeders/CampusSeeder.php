<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    /**
     * Every school starts with one main campus; seeded users belong to it.
     */
    public function run(): void
    {
        $campus = Campus::withoutEvents(fn (): Campus => Campus::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Campus',
                'address' => 'Main Campus Road',
                'city' => 'Lahore',
                'phone' => '+92 300 1234567',
                'email' => 'info@pinnacle.edu.pk',
                'is_active' => true,
            ],
        ));

        User::withoutEvents(fn () => User::query()->whereNull('campus_id')->update(['campus_id' => $campus->id]));
    }
}
