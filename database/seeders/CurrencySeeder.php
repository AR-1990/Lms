<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::upsert([
            ['country_id' => Country::query()->where('iso2', 'PK')->value('id'), 'name' => 'Pakistani Rupee', 'code' => 'PKR', 'symbol' => 'Rs', 'precision' => 2, 'is_active' => true],
            ['country_id' => Country::query()->where('iso2', 'US')->value('id'), 'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'precision' => 2, 'is_active' => true],
            ['country_id' => Country::query()->where('iso2', 'AE')->value('id'), 'name' => 'UAE Dirham', 'code' => 'AED', 'symbol' => 'AED', 'precision' => 2, 'is_active' => true],
            ['country_id' => Country::query()->where('iso2', 'SA')->value('id'), 'name' => 'Saudi Riyal', 'code' => 'SAR', 'symbol' => 'SAR', 'precision' => 2, 'is_active' => true],
            ['country_id' => Country::query()->where('iso2', 'GB')->value('id'), 'name' => 'Pound Sterling', 'code' => 'GBP', 'symbol' => 'GBP', 'precision' => 2, 'is_active' => true],
        ], ['code'], ['country_id', 'name', 'symbol', 'precision', 'is_active']);
    }
}
