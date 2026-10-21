<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\PortalAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up seeded roles, permissions, and demo users.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_parent_can_login_and_open_parent_portal_pages(): void
    {
        $response = $this->post('/login', [
            'role' => 'parent',
            'username' => 'parent@lms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk();
        $this->get('/parent/children')->assertOk();
        $this->get('/parent/fees')->assertOk();
        $this->get('/parent/notices')->assertOk();
    }

    public function test_admin_can_enter_parent_portal_flow(): void
    {
        $response = $this->post('/login', [
            'role' => 'parent',
            'username' => 'admin@lms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->get('/parent/children')->assertOk();
        $this->get('/parent/fees')->assertOk();
        $this->get('/parent/notices')->assertOk();
    }

    public function test_admin_always_lands_on_admin_dashboard_and_can_see_settings_option(): void
    {
        $response = $this->post('/login', [
            'role' => 'student',
            'username' => 'admin@lms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboards.admin')
            ->assertSee('Super Administrator')
            ->assertSee('Role Mix')
            ->assertSee(route('admin.users'))
            ->assertSee(route('admin.roles'))
            ->assertSee(route('admin.reports'))
            ->assertSee(route('admin.settings'))
            ->assertDontSee('Teacher Classes')
            ->assertDontSee('Accounts Payroll');
    }

    public function test_admin_dashboard_shows_live_user_counts(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertViewHas('data', fn (array $data): bool => $data['overview']['total_users'] === 5
            && $data['overview']['total_teachers'] === 1
            && $data['overview']['total_students'] === 1
            && $data['overview']['total_roles'] === 5);
        $response->assertSeeInOrder(['Total Users', 'data-count-up="5"'], false);
    }

    public function test_admin_dashboard_charts_show_signups_for_the_last_six_months(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();
        User::factory()->create(['created_at' => now()->subMonths(2)->startOfMonth()->addDay()]);
        User::factory()->create(['created_at' => now()->subMonths(8)]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertViewHas('data', function (array $data): bool {
            $counts = array_column($data['monthly_signups'], 'count');

            return count($counts) === 6
                && $counts[3] === 1
                && $counts[5] === 5
                && array_sum($counts) === 6
                && $data['overview']['new_this_month'] === 5
                && $data['overview']['total_users'] === 7;
        });
        $response->assertSee('Account Growth');
        $response->assertSee('Role Mix');
    }

    public function test_non_admin_dashboard_does_not_link_to_admin_pages(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertViewIs('dashboards.portal');
        $response->assertDontSee(route('admin.users'));
        $response->assertDontSee(route('admin.settings'));
    }

    public function test_navigation_marks_the_current_page_link_as_active(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $response = $this->actingAs($admin)->get('/admin/roles');

        $response->assertSeeInOrder([
            'href="'.route('admin.roles').'"',
            'class="dash-nav-link is-active"',
            'aria-current="page"',
        ], false);
    }

    public function test_dashboard_renders_collapsed_sidebar_from_cookie(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $this->actingAs($admin)
            ->withUnencryptedCookie('dash_sidebar', 'collapsed')
            ->get('/dashboard')
            ->assertSee('class="dash-body is-sidebar-collapsed"', false);
    }

    public function test_dashboard_renders_expanded_sidebar_without_cookie(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertSee('class="dash-body"', false);
    }

    public function test_dashboard_shows_flash_status_in_banner(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $response = $this->actingAs($admin)
            ->withSession(['status' => 'System settings updated successfully.'])
            ->get('/dashboard');

        $response->assertSeeInOrder(['dash-banner-success', 'System settings updated successfully.'], false);
    }

    public function test_login_page_hides_demo_credentials_outside_local_environment(): void
    {
        $this->get('/login')->assertDontSee('Instant Demo Autofill');
    }

    public function test_login_page_shows_demo_credentials_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->get('/login')->assertSee('Instant Demo Autofill');
    }

    public function test_custom_role_can_login_to_accounts_portal_through_permissions(): void
    {
        $financeRole = Role::create([
            'name' => 'Finance Officer',
            'slug' => 'finance-officer',
            'description' => 'Handles finance portal operations.',
        ]);
        $financeRole->syncPermissions(['manage-fees', 'view-fees', 'view-payroll']);

        User::create([
            'name' => 'Finance Officer',
            'email' => 'finance@lms.test',
            'password' => 'password',
        ])->syncRoles(['finance-officer']);

        $response = $this->post('/login', [
            'role' => 'accounts',
            'username' => 'finance@lms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk();
        $this->get('/accounts/collections')->assertOk();
        $this->get('/accounts/challans')->assertOk();
        $this->get('/accounts/payroll')->assertOk();
    }

    public function test_portal_keys_are_loaded_from_roles_table_dashboard_views(): void
    {
        Role::query()
            ->where('slug', 'teacher')
            ->update(['dashboard_view' => null]);

        $portalKeys = app(PortalAccessService::class)->getPortalKeys();

        $this->assertNotContains('teacher', $portalKeys);
        $this->assertContains('student', $portalKeys);
    }

    public function test_admin_settings_page_restores_reference_dropdown_data_when_tables_are_empty(): void
    {
        Region::query()->delete();
        Currency::query()->delete();
        Country::query()->delete();

        $response = $this->post('/login', [
            'role' => 'admin',
            'username' => 'admin@lms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Pakistan')
            ->assertSee('Punjab')
            ->assertSee('PKR');
    }
}
