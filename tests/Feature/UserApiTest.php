<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    /**
     * Set up seeded roles, permissions, users and the main campus.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->student = User::where('email', 'student@lms.test')->first();
    }

    public function test_user_without_manage_users_permission_is_forbidden(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/users/datatable')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->deleteJson('/api/erp/users/'.$this->student->id)->assertForbidden();

        $this->assertNotSoftDeleted($this->student);
    }

    public function test_index_returns_paginated_resources_without_secrets(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/users?role=parent');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.email', 'parent@lms.test')
            ->assertJsonPath('data.items.0.phone', '03001234567')
            ->assertJsonPath('data.items.0.roles.0.slug', 'parent')
            ->assertJsonPath('data.items.0.campus.code', 'MAIN')
            ->assertJsonMissingPath('data.items.0.password')
            ->assertJsonMissingPath('data.items.0.remember_token');
    }

    public function test_datatable_filters_by_role_status_and_search_across_sign_in_fields(): void
    {
        User::where('email', 'teacher@lms.test')->update(['is_active' => false]);

        $byRole = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/users/datatable?draw=1&role=accounts');
        $this->assertSame(['accounts@lms.test'], array_column($byRole->json('data.data'), 'email'));

        $disabled = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/users/datatable?draw=1&is_active=0');
        $this->assertSame(['teacher@lms.test'], array_column($disabled->json('data.data'), 'email'));

        $byUsername = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/users/datatable?'.http_build_query([
            'draw' => 1,
            'columns' => [['data' => 'name', 'searchable' => 'true'], ['data' => 'username', 'searchable' => 'true']],
            'search' => ['value' => 'PIA-2026', 'regex' => 'false'],
        ]));
        $this->assertSame(['student@lms.test'], array_column($byUsername->json('data.data'), 'email'));
    }

    public function test_datatable_marks_the_signed_in_user_and_lists_roles(): void
    {
        $rows = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/users/datatable?draw=1')->json('data.data'))->keyBy('email');

        $this->assertTrue($rows['admin@lms.test']['is_self']);
        $this->assertFalse($rows['student@lms.test']['is_self']);
        $this->assertSame([['slug' => 'student', 'name' => 'Student']], $rows['student@lms.test']['roles_list']);
    }

    public function test_store_creates_user_with_roles_and_logs_it(): void
    {
        $campus = Campus::first();

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/users', [
            'name' => 'Hina Raza',
            'email' => 'hina@lms.test',
            'username' => 'hina.r',
            'phone' => '+923001112233',
            'campus_id' => $campus->id,
            'password' => 'secret-pass',
            'roles' => ['teacher'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.username', 'hina.r')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.campus.id', $campus->id)
            ->assertJsonPath('data.roles.0.slug', 'teacher');

        $created = User::where('email', 'hina@lms.test')->first();
        $this->assertTrue($created->hasRole('teacher'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'users', 'action' => 'created', 'subject_id' => $created->id]);
    }

    public function test_store_rejects_unknown_roles_and_role_given_as_string(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/users', [
            'name' => 'Ghost',
            'email' => 'ghost@lms.test',
            'password' => 'secret-pass',
            'roles' => ['wizard'],
        ])->assertUnprocessable()->assertJsonPath('errors.first_key', 'roles.0');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/users', [
            'name' => 'Ghost',
            'email' => 'ghost@lms.test',
            'password' => 'secret-pass',
            'roles' => 'admin',
        ])->assertUnprocessable()->assertJsonPath('errors.first_key', 'roles');

        $this->assertDatabaseMissing('users', ['email' => 'ghost@lms.test']);
    }

    public function test_update_changes_roles_and_logs_the_difference(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/users/'.$this->student->id, ['roles' => ['student', 'parent']])
            ->assertOk()
            ->assertJsonPath('message', 'User updated.');

        $log = ActivityLog::where('action', 'roles_changed')->where('subject_id', $this->student->id)->with('changes')->firstOrFail();

        $this->assertSame('student', $log->changes->first()->old_value);
        $this->assertSame('parent, student', $log->changes->first()->new_value);
    }

    public function test_admin_cannot_strip_own_admin_role_or_disable_self_through_the_api(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/users/'.$this->admin->id, ['roles' => ['teacher']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.roles.0', 'You cannot remove the Administrator role from your own account.');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/users/'.$this->admin->id, ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.is_active.0', 'You cannot disable your own account.');

        $this->assertTrue($this->admin->hasRole('admin'));
        $this->assertTrue($this->admin->refresh()->is_active);
    }

    public function test_destroy_soft_deletes_and_signs_the_user_out_everywhere(): void
    {
        $this->student->createToken('android');
        UserDevice::create(['user_id' => $this->student->id, 'device_id' => 'phone-1', 'platform' => 'android', 'is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/users/'.$this->student->id)
            ->assertOk()
            ->assertJsonPath('message', 'User deleted.');

        $this->assertSoftDeleted($this->student);
        $this->assertSame(0, $this->student->tokens()->count());
        $this->assertFalse(UserDevice::where('user_id', $this->student->id)->value('is_active'));
        $this->assertNull(User::findForLogin('student@lms.test'));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/users/'.$this->admin->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.user.0', 'You cannot delete your own account.');

        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_missing_user_returns_not_found_envelope(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/users/9999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
