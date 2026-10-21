<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Lookup;
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
        $this->actingAs($teacher)->get('/admin/users/'.$this->admin->id)->assertForbidden();
    }

    public function test_index_renders_the_grid_shell_with_counts_and_role_filter(): void
    {
        User::where('email', 'parent@lms.test')->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)->get('/admin/users');

        $response->assertOk()
            ->assertSee('data-datatable-url="'.route('erp.users.datatable').'"', false)
            ->assertSee('data-resource-url="'.route('erp.users.index').'"', false)
            ->assertSee('data-count-up="5"', false)
            ->assertSee('data-count-up="4"', false)
            ->assertSee('<option value="teacher">Teacher</option>', false)
            ->assertSee('js/admin-users.js', false);
    }

    public function test_create_page_shows_role_campus_and_sign_in_fields(): void
    {
        $campus = Campus::first();

        $this->actingAs($this->admin)
            ->get('/admin/users/create')
            ->assertSee('Profile Information')
            ->assertSee('value="teacher"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('<option value="'.$campus->id.'"', false)
            ->assertSee('Create User');
    }

    public function test_campus_list_hides_inactive_campuses_except_the_one_already_assigned(): void
    {
        $closed = Campus::factory()->inactive()->create(['name' => 'Closed Branch']);
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->get('/admin/users/create')->assertDontSee('Closed Branch');

        $teacher->update(['campus_id' => $closed->id]);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $teacher))->assertSee('Closed Branch');
    }

    public function test_admin_can_create_user_with_sign_in_details_and_campus(): void
    {
        $campus = Campus::first();

        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Bilal Ahmed',
            'email' => 'bilal@lms.test',
            'username' => 'bilal.a',
            'phone' => '0300 7654321',
            'campus_id' => $campus->id,
            'is_active' => '1',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'roles' => ['teacher'],
        ]);

        $created = User::where('email', 'bilal@lms.test')->firstOrFail();

        $response->assertRedirect(route('admin.users.show', $created));
        $this->assertSame('bilal.a', $created->username);
        $this->assertSame('0300 7654321', $created->phone);
        $this->assertSame($campus->id, $created->campus_id);
        $this->assertTrue($created->is_active);
    }

    public function test_create_rejects_taken_username_and_phone_and_bad_formats(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Copy Cat',
            'email' => 'copy@lms.test',
            'username' => 'admin',
            'phone' => '03001234567',
            'campus_id' => 9999,
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'roles' => ['teacher'],
        ]);

        $response->assertSessionHasErrors(['username', 'phone', 'campus_id']);

        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Odd Format',
            'email' => 'odd@lms.test',
            'username' => 'has space',
            'phone' => 'call me',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'roles' => ['teacher'],
        ])->assertSessionHasErrors([
            'username' => 'Use only letters, numbers, dots, dashes and underscores.',
            'phone' => 'Enter a valid phone number, e.g. 03001234567.',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'copy@lms.test']);
        $this->assertDatabaseMissing('users', ['email' => 'odd@lms.test']);
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
            ->assertSee('03001234567')
            ->assertSee('Access &amp; Permissions', false)
            ->assertSee('View Children')
            ->assertSee('data-owner-id="'.$parent->id.'"', false)
            ->assertSee('<option value="'.Lookup::where('type', 'document_type')->where('name', 'CNIC')->value('id').'">CNIC</option>', false)
            ->assertSee('data-delete-url="'.route('erp.users.destroy', $parent).'"', false);
    }

    public function test_show_page_hides_delete_on_own_profile(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->admin))
            ->assertOk()
            ->assertDontSee('data-user-delete', false);
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
        $this->assertSame('Super Administrator', $this->admin->refresh()->name);
    }

    public function test_admin_cannot_disable_own_account(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'is_active' => '0',
            'roles' => ['admin'],
        ]);

        $response->assertSessionHasErrors(['is_active' => 'You cannot disable your own account.']);
        $this->assertTrue($this->admin->refresh()->is_active);
    }

    public function test_disabling_a_user_signs_them_out_of_every_device(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $teacher->createToken('android');

        $this->actingAs($this->admin)->put(route('admin.users.update', $teacher), [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'is_active' => '0',
            'roles' => ['teacher'],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($teacher->refresh()->is_active);
        $this->assertSame(0, $teacher->tokens()->count());
    }
}
