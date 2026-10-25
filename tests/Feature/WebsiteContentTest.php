<?php

namespace Tests\Feature;

use App\Models\AdmissionInquiry;
use App\Models\Lookup;
use App\Models\SchoolClass;
use App\Models\User;
use App\Models\WebsitePost;
use App\Models\WebsiteStat;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    public function test_home_page_shows_database_content_and_the_real_fee_structure(): void
    {
        WebsiteStat::where('label', 'Certified Educators')->update(['value' => 145]);
        WebsitePost::where('show_in_ticker', true)->first()->update(['title' => 'Winter vacation starts on 20 December']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-target="145"', false)
            ->assertSee('Winter vacation starts on 20 December')
            ->assertSee('Dr. Farhan Qureshi')
            ->assertSee('data-monthly="1150000"', false)
            ->assertSee('Sibling (10%)');
    }

    public function test_hidden_and_unpublished_content_stays_off_the_site(): void
    {
        WebsiteStat::where('label', 'Certified Educators')->update(['is_active' => false]);
        WebsitePost::where('is_featured', true)->update(['is_published' => false]);

        $this->get('/')->assertOk()->assertDontSee('Certified Educators');
        $this->get('/news')->assertOk()->assertDontSee('Pinnacle Robotics Team Triumphs');
        $this->get('/contact')->assertOk()->assertSee('What is the minimum age requirement for Montessori admission?');
    }

    public function test_website_forms_create_admission_inquiries_from_the_website_source(): void
    {
        $grade5 = SchoolClass::where('name', 'Grade 5')->first();

        $this->postJson('/api/inquiry', [
            'parent_name' => 'Imran Khan',
            'child_name' => 'Daniyal Khan',
            'phone' => '03211234567',
            'class_sought_id' => $grade5->id,
            'topic_id' => Lookup::where('type', 'contact_topic')->where('name', 'Campus Visit')->value('id'),
            'message' => 'We would like to visit on Saturday.',
        ])->assertCreated()->assertJsonPath('success', true);

        $inquiry = AdmissionInquiry::where('phone', '03211234567')->first();
        $this->assertSame('Imran Khan', $inquiry->parent_name);
        $this->assertSame($grade5->id, $inquiry->class_sought_id);
        $this->assertSame('Website', $inquiry->source?->name);
        $this->assertSame('[Campus Visit] We would like to visit on Saturday.', $inquiry->message);

        $this->postJson('/api/inquiry', ['parent_name' => 'No Phone', 'message' => 'Hi'])->assertUnprocessable();
    }

    public function test_every_inquiry_form_sends_the_fields_the_api_expects(): void
    {
        $accepted = ['_token', 'parent_name', 'child_name', 'email', 'phone', 'class_sought_id', 'topic_id', 'message'];

        foreach (['/', '/admissions', '/contact'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('data-helpline', false)->getContent();
            preg_match_all('/<form data-ajax-inquiry.*?<\/form>/s', $html, $forms);
            $this->assertNotEmpty($forms[0], $url);

            foreach ($forms[0] as $form) {
                preg_match_all('/name="([a-z_]+)"/', $form, $names);
                $this->assertSame([], array_values(array_diff(array_unique($names[1]), $accepted)), "{$url} sends fields the API ignores");
                $this->assertEmpty(array_diff(['parent_name', 'phone', 'message'], $names[1]), "{$url} misses a required field");
            }
        }

        $this->get('/login')->assertOk()->assertDontSee('data-helpline', false);
        $this->postJson('/api/inquiry', ['phone' => '0321'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.parent_name.0', 'Please tell us your name.')
            ->assertJsonPath('errors.fields.message.0', 'Please write a short message.');
    }

    public function test_admin_manages_website_content_and_others_cannot(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $post = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/website/posts', ['title' => 'Sports Day 2026', 'show_in_ticker' => true, 'published_on' => '2026-10-01'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'sports-day-2026')
            ->json('data');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/website/posts/datatable?draw=1')->assertOk()->assertJsonPath('data.recordsTotal', 8);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/website/stats', ['label' => 'Campuses'])->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/website/banners/datatable')->assertNotFound();

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/website/posts/'.$post['id'])->assertOk();
        $this->assertSoftDeleted('website_posts', ['id' => $post['id']]);

        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/website/faqs', ['question' => 'Q?', 'answer' => 'A.'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.website.posts'))->assertOk()->assertSee('data-website="posts"', false);
        $this->actingAs($teacher)->get(route('admin.website.faqs'))->assertForbidden();
    }
}
