<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_open_system_settings_page(): void
    {
        $admin = User::query()->where('email', 'admin@lms.test')->first();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('School Profile');
    }

    public function test_admin_can_update_system_settings_and_timezone_is_reflected_on_dashboard(): void
    {
        $admin = User::query()->where('email', 'admin@lms.test')->first();
        $region = Region::query()->where('slug', 'dubai')->first();
        $currency = Currency::query()->where('code', 'AED')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/erp/admin/settings', [
                'school_name' => 'Pinnacle Gulf Campus',
                'school_address' => 'Sheikh Zayed Road, Dubai',
                'school_phone' => '+971 50 0000000',
                'country_id' => $region->country_id,
                'region_id' => $region->id,
                'currency_id' => $currency->id,
                'logo' => UploadedFile::fake()->image('school-logo.png'),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.school_name', 'Pinnacle Gulf Campus')
            ->assertJsonPath('data.region.timezone', 'Asia/Dubai')
            ->assertJsonPath('data.currency.code', 'AED');

        $settings = SystemSetting::query()->first();

        $this->assertNotNull($settings);
        $this->assertSame('Pinnacle Gulf Campus', $settings->school_name);
        $this->assertNotNull($settings->logo_binary);
        $this->assertSame('image/png', $settings->logo_mime_type);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/erp/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.system_status.timezone', 'Asia/Dubai')
            ->assertJsonPath('data.system_status.school_name', 'Pinnacle Gulf Campus');
    }
}
