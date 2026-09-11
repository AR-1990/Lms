<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::upsert([
            ['name' => 'Pakistan', 'iso2' => 'PK', 'iso3' => 'PAK', 'phone_code' => '+92', 'is_active' => true],
            ['name' => 'United Arab Emirates', 'iso2' => 'AE', 'iso3' => 'ARE', 'phone_code' => '+971', 'is_active' => true],
            ['name' => 'Saudi Arabia', 'iso2' => 'SA', 'iso3' => 'SAU', 'phone_code' => '+966', 'is_active' => true],
            ['name' => 'United Kingdom', 'iso2' => 'GB', 'iso3' => 'GBR', 'phone_code' => '+44', 'is_active' => true],
            ['name' => 'United States', 'iso2' => 'US', 'iso3' => 'USA', 'phone_code' => '+1', 'is_active' => true],
        ], ['iso2'], ['name', 'iso3', 'phone_code', 'is_active']);
    }
}
