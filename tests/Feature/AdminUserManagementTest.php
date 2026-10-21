<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * Set up seeded roles, permissions, and demo users.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_user_without_manage_users_permission_is_forbidden(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher)->get('/admin/users')->assertForbidden();
        $this->actingAs($teacher)->post('/admin/users', [])->assertForbidden();
        $this->actingAs($teacher)->delete('/admin/users/'.$this->admin->id)->assertForbidden();

        $this->assertModelExists($this->admin);
    }

    public function test_index_lists_users_with_view_edit_and_delete_actions(): void
    {
        $student = User::where('email', 'student@lms.test')->first();

        $response = $this->actingAs($this->admin)->get('/admin/users');

        $response->assertSee('student@lms.test');
        $response->assertSee(route('admin.users.show', $student));
        $response->assertSee(route('admin.users.edit', $student));
        $response->assertSee(route('admin.users.destroy', $student));
        $response->assertSee('dash-action-view', false);
        $response->assertSee('dash-action-edit', false);
        $response->assertSee('dash-action-delete', false);
    }

    public function test_index_filters_by_search_and_role(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users?search=Jenkins')
            ->assertSee('teacher@lms.test')
            ->assertDontSee('student@lms.test');

        $this->actingAs($this->admin)
            ->get('/admin/users?role=parent')
            ->assertSee('parent@lms.test')
            ->assertDontSee('accounts@lms.test');
    }

    public function test_admin_cannot_delete_own_account_from_the_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/users');

        $response->assertDontSee('data-confirm-name="Super Administrator"', false);
        $response->assertSee('data-confirm-name="John Doe"', false);
        $response->assertSee('This is you');
    }

    public function test_create_page_shows_role_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users/create')
            ->assertSee('Profile Information')
            ->assertSee('value="teacher"', false)
            ->assertSee('Create User');
    }

    public function test_admin_can_create_user_with_roles(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@lms.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'roles' => ['teacher', 'parent'],
        ]);

        $created = User::where('email', 'ayesha@lms.test')->first();

        $response->assertRedirect(route('admin.users.show', $created));
        $response->assertSessionHas('status', 'Ayesha Khan has been added.');
        $this->assertSame('Ayesha Khan', $created->name);
        $this->assertTrue(Hash::check('secret-pass', $created->password));
        $this->assertSame(['parent', 'teacher'], $created->roles()->orderBy('slug')->pluck('slug')->all());
    }

    public function test_create_requires_all_fields(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
        $response->assertSessionHasErrors(['roles' => 'Select at least one role for this user.']);
        $this->assertSame(5, User::count());
    }

    public function test_create_rejects_duplicate_email_and_mismatched_password(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Copy Cat',
            'email' => 'teacher@lms.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'different-pass',
            'roles' => ['teacher'],
        ]);

        $response->assertSessionHasErrors([
            'email' => 'The email has already been taken.',
            'password' => 'The password field confirmation does not match.',
        ]);
        $this->assertSame(5, User::count());
    }

    public function test_create_rejects_unknown_role(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Ghost Role',
            'email' => 'ghost@lms.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'roles' => ['wizard'],
        ]);

        $response->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('users', ['email' => 'ghost@lms.test']);
    }

    public function test_show_page_displays_user_and_permissions(): void
    {
        $parent = User::where('email', 'parent@lms.test')->first();

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $parent))
            ->assertSee('Farhan Malik')
            ->assertSee('parent@lms.test')
            ->assertSee('Access &amp; Permissions', false)
            ->assertSee('View Children');
    }

    public function test_show_returns_not_found_for_missing_user(): void
    {
        $this->actingAs($this->admin)->get('/admin/users/9999')->assertNotFound();
    }

    public function test_edit_page_is_prefilled(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $teacher))
            ->assertSee('value="teacher@lms.test"', false)
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_user_and_keep_password_when_blank(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $originalPasswordHash = $teacher->password;

        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $teacher), [
            'name' => 'Sarah Jenkins',
            'email' => 'sarah@lms.test',
            'password' => '',
            'password_confirmation' => '',
            'roles' => ['accounts'],
        ]);

        $teacher->refresh();

        $response->assertRedirect(route('admin.users.show', $teacher));
        $response->assertSessionHas('status', 'Sarah Jenkins has been updated.');
        $this->assertSame('Sarah Jenkins', $teacher->name);
        $this->assertSame('sarah@lms.test', $teacher->email);
        $this->assertSame($originalPasswordHash, $teacher->password);
        $this->assertSame(['accounts'], $teacher->roles()->pluck('slug')->all());
    }

    public function test_admin_can_change_user_password(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->put(route('admin.users.update', $teacher), [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'roles' => ['teacher'],
        ]);

        $this->assertTrue(Hash::check('brand-new-pass', $teacher->refresh()->password));
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => ['teacher'],
        ]);

        $response->assertSessionHasErrors(['roles' => 'You cannot remove the Administrator role from your own account.']);
        $this->assertTrue($this->admin->hasRole('admin'));
    }

    public function test_admin_can_delete_another_user(): void
    {
        $student = User::where('email', 'student@lms.test')->first();

        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $student));

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('status', 'John Doe has been deleted.');
        $this->assertModelMissing($student);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin));

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('error', 'You cannot delete your own account.');
        $this->assertModelExists($this->admin);
    }
}
