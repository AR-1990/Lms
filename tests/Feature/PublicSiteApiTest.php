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
}
