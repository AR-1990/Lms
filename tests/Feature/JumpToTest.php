<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JumpToTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_jump_to_lists_only_the_pages_each_user_may_open(): void
    {
        $admin = $this->actingAs(User::where('email', 'admin@lms.test')->first(), 'sanctum')->getJson('/api/erp/jump-to')->assertOk()->json('data');
        $urls = collect($admin)->pluck('url');

        $this->assertContains(['label' => 'Shifts', 'group' => 'HR', 'url' => url('admin/hr/shifts')], $admin);
        $this->assertContains(['label' => 'Online Tests', 'group' => 'Exams', 'url' => url('admin/exams/online-tests')], $admin);
        $this->assertFalse($urls->contains(url('accounts/challans')), 'redirect aliases are left out');
        $this->assertFalse($urls->contains(url('student/grades')), 'student pages are for students');

        $teacher = collect($this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->getJson('/api/erp/jump-to')->assertOk()->json('data'))->pluck('url');
        $this->assertTrue($teacher->contains(url('admin/exams/marks')));
        $this->assertFalse($teacher->contains(url('admin/settings')));

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/erp/jump-to')->assertUnauthorized();
    }
}
