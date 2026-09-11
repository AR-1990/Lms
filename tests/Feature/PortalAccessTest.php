<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
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

        $this->seed(RolePermissionSeeder::class);
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
}
