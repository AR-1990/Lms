<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_role_has_the_guide_in_the_sidebar_with_only_its_own_topics(): void
    {
        foreach (['admin', 'teacher', 'student', 'parent', 'accounts'] as $role) {
            $this->actingAs(User::where('email', "{$role}@lms.test")->first())->followingRedirects()->get(route('dashboard'))
                ->assertOk()->assertSee(route('guide'), false)->assertSee('User Guide');
        }

        $this->actingAs(User::where('email', 'admin@lms.test')->first())->get(route('guide'))->assertOk()
            ->assertSee('id="library"', false)->assertSee('id="administration"', false)->assertSee('id="exams"', false);

        $this->actingAs(User::where('email', 'parent@lms.test')->first())->get(route('guide'))->assertOk()
            ->assertSee('id="parent"', false)->assertSee('Paid online? Send the screenshot', false)
            ->assertDontSee('id="administration"', false)->assertDontSee('id="library"', false);

        $this->actingAs(User::where('email', 'teacher@lms.test')->first())->get(route('guide'))->assertOk()
            ->assertSee('id="teaching"', false)->assertSee('id="my-work"', false)
            ->assertDontSee('id="administration"', false)->assertDontSee('id="parent"', false);

        $this->actingAs(User::where('email', 'student@lms.test')->first())->get(route('guide'))->assertOk()
            ->assertSee('id="student"', false)->assertDontSee('id="fees"', false);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('guide'))->assertRedirect(route('login'));
    }
}
