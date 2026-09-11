<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pakistanId = Country::query()->where('iso2', 'PK')->value('id');
        $uaeId = Country::query()->where('iso2', 'AE')->value('id');
        $saudiId = Country::query()->where('iso2', 'SA')->value('id');
        $ukId = Country::query()->where('iso2', 'GB')->value('id');
        $usaId = Country::query()->where('iso2', 'US')->value('id');

        Region::upsert([
            ['country_id' => $pakistanId, 'name' => 'Punjab', 'slug' => 'punjab', 'timezone' => 'Asia/Karachi', 'is_active' => true],
            ['country_id' => $pakistanId, 'name' => 'Sindh', 'slug' => 'sindh', 'timezone' => 'Asia/Karachi', 'is_active' => true],
            ['country_id' => $pakistanId, 'name' => 'Islamabad Capital Territory', 'slug' => 'islamabad-capital-territory', 'timezone' => 'Asia/Karachi', 'is_active' => true],
            ['country_id' => $uaeId, 'name' => 'Dubai', 'slug' => 'dubai', 'timezone' => 'Asia/Dubai', 'is_active' => true],
            ['country_id' => $uaeId, 'name' => 'Abu Dhabi', 'slug' => 'abu-dhabi', 'timezone' => 'Asia/Dubai', 'is_active' => true],
            ['country_id' => $saudiId, 'name' => 'Riyadh', 'slug' => 'riyadh', 'timezone' => 'Asia/Riyadh', 'is_active' => true],
            ['country_id' => $saudiId, 'name' => 'Jeddah', 'slug' => 'jeddah', 'timezone' => 'Asia/Riyadh', 'is_active' => true],
            ['country_id' => $ukId, 'name' => 'London', 'slug' => 'london', 'timezone' => 'Europe/London', 'is_active' => true],
            ['country_id' => $usaId, 'name' => 'New York', 'slug' => 'new-york', 'timezone' => 'America/New_York', 'is_active' => true],
            ['country_id' => $usaId, 'name' => 'California', 'slug' => 'california', 'timezone' => 'America/Los_Angeles', 'is_active' => true],
        ], ['slug'], ['country_id', 'name', 'timezone', 'is_active']);
    }
}
