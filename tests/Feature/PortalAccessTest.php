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
            ->assertSee('Welcome to LMS')
            ->assertSee('Settings')
            ->assertDontSee('Teacher Classes')
            ->assertDontSee('Accounts Payroll');
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
