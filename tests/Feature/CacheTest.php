<?php

namespace Tests\Feature;

use App\Models\Lookup;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use App\Services\LookupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CacheTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
    }

    public function test_a_permission_given_to_a_role_works_at_once_and_taking_it_away_too(): void
    {
        $this->assertFalse($this->teacher->hasPermission('manage-transport'));
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/vehicles/datatable')->assertForbidden();

        $teacherRole = Role::where('slug', 'teacher')->first();
        $granted = [...$teacherRole->permissions()->pluck('slug')->all(), 'manage-transport'];
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/roles/{$teacherRole->id}/permissions", ['permissions' => $granted])->assertOk();

        $this->assertTrue(User::find($this->teacher->id)->hasPermission('manage-transport'));
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::find($this->teacher->id), 'sanctum')->getJson('/api/erp/vehicles/datatable')->assertOk();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/roles/{$teacherRole->id}/permissions", ['permissions' => array_values(array_diff($granted, ['manage-transport']))])->assertOk();
        $this->assertFalse(User::find($this->teacher->id)->hasPermission('manage-transport'));
    }

    public function test_changing_a_users_roles_refreshes_their_access(): void
    {
        $this->assertFalse($this->teacher->hasRole('accounts'));

        $this->teacher->assignRole('accounts');
        $this->assertTrue($this->teacher->hasRole('accounts'));
        $this->assertTrue(User::find($this->teacher->id)->hasPermission('manage-expenses'));

        $this->teacher->removeRole('accounts');
        $this->assertFalse(User::find($this->teacher->id)->hasPermission('manage-expenses'));
    }

    public function test_dropdown_lists_and_website_pages_show_changes_straight_away(): void
    {
        $lookups = app(LookupService::class);
        $this->assertNotContains('Buddhism', $lookups->options('religion')->pluck('name')->all());

        Lookup::create(['type' => 'religion', 'name' => 'Buddhism', 'is_active' => true]);
        $this->assertContains('Buddhism', $lookups->options('religion')->pluck('name')->all());

        $about = WebsitePage::where('slug', 'about')->first();
        $this->get('/about')->assertOk()->assertDontSee('Built on Trust');
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/website/pages/'.$about->id, ['name' => 'About Us', 'hero_title' => 'Built on Trust', 'is_published' => true])
            ->assertOk();
        $this->get('/about')->assertOk()->assertSee('Built on Trust');
    }

    public function test_repeat_page_loads_need_fewer_database_queries(): void
    {
        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->actingAs($this->admin);
        $first = $count('/admin/hr/leave-types');
        $second = $count('/admin/hr/leave-types');

        $this->assertLessThan($first, $second);
    }
}
