<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Lookup;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
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
        $response->assertSeeInOrder(['Role Mix', '5 portal accounts', 'data-count-up="5"'], false);
    }

    public function test_admin_dashboard_quote_comes_from_active_daily_quote_options(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();
        Lookup::where('type', 'daily_quote')->update(['is_active' => false]);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('dash-quote', false);

        Lookup::create(['type' => 'daily_quote', 'name' => 'Read a page every day.', 'description' => 'School motto', 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertSee('Read a page every day.')
            ->assertSee('School motto');
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
        $response->assertSee('5 new this month');
        $response->assertSee('Role Mix');
    }

    public function test_non_admin_dashboard_does_not_link_to_admin_pages(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertViewIs('dashboards.teacher');
        $response->assertDontSee(route('admin.users'));
        $response->assertDontSee(route('admin.settings'));
    }

    public function test_a_role_given_an_extra_permission_gets_that_screens_link(): void
    {
        $accountant = User::where('email', 'accounts@lms.test')->first();

        $this->actingAs($accountant)->get('/dashboard')->assertDontSee(route('admin.cards.index'));

        $role = Role::where('slug', 'accounts')->first();
        $role->permissions()->attach(Permission::where('slug', 'manage-cards')->value('id'));

        $this->actingAs($accountant->fresh())->get('/dashboard')
            ->assertSee(route('admin.cards.index'))
            ->assertDontSee(route('admin.users'));
        $this->get(route('admin.cards.index'))->assertOk();
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

    public function test_login_page_has_one_form_without_demo_logins_or_portal_picker(): void
    {
        $this->app['env'] = 'local';

        $this->get('/login')
            ->assertOk()
            ->assertSee((string) SystemSetting::query()->value('school_name'))
            ->assertDontSee('Demo')
            ->assertDontSee('lms.test')
            ->assertDontSee('name="role"', false)
            ->assertDontSee('role-chip', false);
    }

    public function test_student_and_teacher_portal_pages_show_their_own_records(): void
    {
        $this->actingAs(User::where('email', 'student@lms.test')->first());
        $this->get('/dashboard')->assertOk()->assertSee('John Doe')->assertSee('Grade 5');
        $this->get('/student/courses')->assertOk();
        $this->get('/student/attendance')->assertOk();
        $this->get('/student/grades')->assertOk();

        $this->actingAs(User::where('email', 'teacher@lms.test')->first());
        $this->get('/dashboard')->assertOk()->assertSee('Sarah Jenkins');
        $this->get('/teacher/classes')->assertOk()->assertSee('English');
    }

    public function test_teacher_signs_in_without_choosing_a_portal(): void
    {
        $this->post('/login', ['username' => 'teacher@lms.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('portal', 'teacher');

        $this->get('/dashboard')->assertOk();
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
            'username' => 'finance@lms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk();
        $this->get('/accounts/collections')->assertRedirect('/accounts/fees/receipts');
        $this->get('/accounts/fees/receipts')->assertOk();
        $this->get('/accounts/fees/challans')->assertOk();
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
