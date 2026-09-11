<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Region;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'school_name' => 'Pinnacle International Academy',
                'school_address' => 'Main Campus Road, Lahore, Pakistan',
                'school_phone' => '+92 300 1234567',
                'country_id' => Country::query()->where('iso2', 'PK')->value('id'),
                'region_id' => Region::query()->where('slug', 'punjab')->value('id'),
                'currency_id' => Currency::query()->where('code', 'PKR')->value('id'),
            ],
        );
    }
}
