<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_mobile_apps_read_the_same_public_content_as_the_website(): void
    {
        $this->getJson('/api/site')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.school.school_name', SystemSetting::query()->value('school_name'))
            ->assertJsonPath('data.school.logo_url', null)
            ->assertJsonStructure(['data' => ['shared' => ['sections'], 'home' => ['stats', 'announcements', 'testimonials', 'upcoming_events', 'classes']]]);

        $this->getJson('/api/site/pages/about')->assertOk()->assertJsonPath('data.slug', 'about')->assertJsonStructure(['data' => ['sections' => [['section_key', 'items']]]]);
        $this->getJson('/api/site/pages/no-such-page')->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/api/site/news')->assertOk()->assertJsonStructure(['data' => ['articles', 'events']]);
        $this->getJson('/api/site/contact')->assertOk()->assertJsonStructure(['data' => ['faqs', 'topics', 'classes']]);

        foreach (['/', '/about', '/news', '/contact', '/admissions'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_mobile_app_gets_the_schools_name_logo_and_live_server_before_sign_in(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'school-key', 'broadcasting.connections.reverb.options' => ['host' => 'ws.school.test', 'port' => 443, 'scheme' => 'https']]);

        $this->getJson('/api/site/app-config')->assertOk()
            ->assertJsonPath('data.school_name', SystemSetting::query()->value('school_name'))
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.realtime.key', 'school-key')
            ->assertJsonPath('data.realtime.host', 'ws.school.test')
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonMissingPath('data.realtime.secret');

        SystemSetting::query()->update(['logo_path' => 'branding/logo-20261102.png']);
        $this->getJson('/api/site/app-config')->assertJsonPath('data.logo_version', 'logo-20261102.png')
            ->assertJsonPath('data.logo_url', route('school-logo', ['v' => 'logo-20261102.png']));
    }
}
