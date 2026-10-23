<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsiteSectionItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsitePageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    public function test_admin_edits_page_banner_block_and_card_and_the_site_shows_them(): void
    {
        $about = WebsitePage::where('slug', 'about')->first();
        $section = WebsiteSection::where('website_page_id', $about->id)->has('items')->first();
        $item = $section->items->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/website/pages/'.$about->id, ['name' => 'About Us', 'hero_title' => 'Our [Proud] Story', 'is_published' => true])
            ->assertOk()->assertJsonPath('success', true);
        $this->putJson('/api/erp/website/sections/'.$section->id, ['title' => 'Values We Live By', 'is_active' => true])->assertOk();
        $this->putJson('/api/erp/website/items/'.$item->id, ['title' => 'Kindness First', 'is_active' => true])->assertOk();

        $this->get('/about')
            ->assertOk()
            ->assertSee('Our <span', false)
            ->assertSee('Proud')
            ->assertSee('Values We Live By')
            ->assertSee('Kindness First');
    }

    public function test_hidden_cards_and_unpublished_pages_stay_off_the_site(): void
    {
        $about = WebsitePage::where('slug', 'about')->first();
        $item = WebsiteSectionItem::whereIn('website_section_id', $about->sections()->pluck('id'))->whereNull('parent_id')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/website/items/'.$item->id, ['title' => 'Secret Card Title', 'is_active' => false])
            ->assertOk();

        $this->get('/about')->assertOk()->assertDontSee('Secret Card Title');
    }

    public function test_links_must_be_site_paths_or_web_addresses(): void
    {
        $section = WebsiteSection::first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/website/sections/'.$section->id, ['cta_url' => 'javascript:alert(1)'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('cta_url', 'errors.fields');

        $this->putJson('/api/erp/website/sections/'.$section->id, ['cta_url' => '/admissions'])->assertOk();
    }

    public function test_removing_a_card_soft_deletes_it_with_its_points(): void
    {
        $card = WebsiteSectionItem::whereNull('parent_id')->has('children')->first();
        $pointIds = $card->children()->pluck('id');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/website/items/'.$card->id, ['reason' => 'Out of date'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSoftDeleted($card);
        $pointIds->each(fn (int $id) => $this->assertSoftDeleted('website_section_items', ['id' => $id]));
    }

    public function test_admin_uploads_a_card_picture(): void
    {
        Storage::fake('public');
        $card = WebsiteSectionItem::first();

        $url = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/website/images/item/'.$card->id, ['image' => UploadedFile::fake()->image('lab.jpg')])
            ->assertOk()
            ->json('data.url');

        $this->assertNotNull($url);
        Storage::disk('public')->assertExists($card->fresh()->image_path);
    }

    public function test_admin_opens_the_page_editor_screen(): void
    {
        $this->actingAs($this->admin)->get(route('admin.website.pages'))->assertOk()->assertSee('data-cms', false);
    }

    public function test_users_without_website_permission_cannot_edit_pages(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $page = WebsitePage::first();

        $this->actingAs($teacher, 'sanctum')
            ->putJson('/api/erp/website/pages/'.$page->id, ['name' => 'Hacked'])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNotSame('Hacked', $page->fresh()->name);
    }
}
