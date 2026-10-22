<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    /**
     * Set up seeded roles, permissions and users.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
    }

    public function test_roles_page_renders_grid_and_grouped_permission_matrix(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee('data-datatable-url="'.route('erp.roles.datatable').'"', false)
            ->assertSee('data-perm-group="academics"', false)
            ->assertSee('value="manage-classes"', false)
            ->assertSee('js/admin-roles.js', false);

        $this->actingAs($this->teacher)->get('/admin/roles')->assertForbidden();
    }

    public function test_roles_api_requires_manage_roles_permission(): void
    {
        $role = Role::where('slug', 'parent')->first();

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/roles/datatable')->assertForbidden();
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/roles', ['name' => 'Hacker'])->assertForbidden();
        $this->actingAs($this->teacher, 'sanctum')->putJson('/api/erp/roles/'.$role->id, ['permissions' => []])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['slug' => 'hacker']);
    }

    public function test_create_derives_slug_from_name_and_grants_permissions(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/roles', [
            'name' => 'Exam Cell',
            'description' => 'Prepares papers and results.',
            'permissions' => ['submit-grades', 'view-grades'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'exam-cell')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.users_count', 0);

        $role = Role::where('slug', 'exam-cell')->first();
        $this->assertSame(['submit-grades', 'view-grades'], $role->permissions()->orderBy('slug')->pluck('slug')->all());
        $this->assertDatabaseHas('activity_logs', ['module' => 'roles', 'action' => 'created', 'subject_id' => $role->id]);
    }

    public function test_create_rejects_duplicate_names_unknown_permissions_and_system_flag(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles', ['name' => 'Teacher'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.slug.0', 'A role with this name or slug already exists.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles', ['name' => 'Clerk', 'permissions' => ['launch-rockets']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'permissions.0');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles', ['name' => 'Locked', 'is_system' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_system', false);
    }

    public function test_permission_changes_are_logged_as_granted_and_revoked(): void
    {
        $role = Role::where('slug', 'parent')->first();
        $before = $role->permissions()->pluck('slug');
        $dropped = $before->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/roles/'.$role->id, ['permissions' => [...$before->slice(1)->values()->all(), 'manage-classes']])
            ->assertOk()
            ->assertJsonPath('message', 'Role updated.');

        $log = ActivityLog::where('action', 'permissions_changed')->where('subject_id', $role->id)->with('changes')->firstOrFail();
        $changes = $log->changes->keyBy('field');

        $this->assertSame('manage-classes', $changes['granted']->new_value);
        $this->assertSame($dropped, $changes['revoked']->old_value);
    }

    public function test_saving_the_same_permissions_writes_no_log(): void
    {
        $role = Role::where('slug', 'parent')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/roles/'.$role->id, ['permissions' => $role->permissions()->pluck('slug')->all()])
            ->assertOk();

        $this->assertSame(0, ActivityLog::where('subject_type', Role::class)->count());
    }

    public function test_administrator_permissions_are_locked_and_system_slugs_fixed(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $teacherRole = Role::where('slug', 'teacher')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/roles/'.$adminRole->id, ['permissions' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.permissions.0', 'The Administrator role always has full access.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles/'.$adminRole->id.'/permissions', ['permissions' => []])
            ->assertUnprocessable();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/roles/'.$teacherRole->id, ['slug' => 'faculty'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.slug.0', 'Built-in roles keep their slug.');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/roles/'.$adminRole->id, ['name' => 'Principal Office'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'admin');
    }

    public function test_role_in_use_cannot_be_deleted_but_unused_custom_role_can(): void
    {
        $custom = Role::create(['name' => 'Librarian', 'slug' => 'librarian']);
        $custom->syncPermissions(['view-courses']);
        $this->teacher->assignRole('librarian');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/roles/'.$custom->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.role.0', 'Remove this role from its 1 user before deleting it.');

        $this->teacher->removeRole('librarian');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/roles/'.$custom->id)
            ->assertOk()
            ->assertJsonPath('message', 'Role deleted.');

        $this->assertSoftDeleted($custom);
        $this->assertNull(Role::where('slug', $custom->slug)->first());
    }

    public function test_assign_revoke_and_sync_user_roles_are_logged_and_protect_own_admin_role(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles/assign-user', ['user_id' => $this->teacher->id, 'role' => 'accounts'])
            ->assertOk()
            ->assertJsonCount(2, 'data.roles');

        $this->assertTrue($this->teacher->hasAllRoles(['accounts', 'teacher']));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles/revoke-user', ['user_id' => $this->teacher->id, 'role' => 'accounts'])
            ->assertOk();

        $this->assertFalse($this->teacher->hasRole('accounts'));
        $this->assertSame(2, ActivityLog::where('action', 'roles_changed')->where('subject_id', $this->teacher->id)->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/roles/sync-user', ['user_id' => $this->admin->id, 'roles' => ['teacher']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.roles.0', 'You cannot remove the Administrator role from your own account.');

        $this->assertTrue($this->admin->hasRole('admin'));
    }

    public function test_datatable_returns_counts_and_flags_for_the_grid(): void
    {
        $rows = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/roles/datatable?draw=1')->json('data.data'))->keyBy('slug');

        $this->assertTrue($rows['admin']['is_admin']);
        $this->assertTrue($rows['teacher']['is_system']);
        $this->assertSame(1, $rows['teacher']['users_count']);
        $this->assertSame(count($rows['teacher']['permission_slugs']), $rows['teacher']['permissions_count']);
        $this->assertArrayNotHasKey('permissions', $rows['teacher']);
    }
}
