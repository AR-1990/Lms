<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Lookup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * Set up seeded roles, permissions, users, and lookups.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    public function test_seeding_does_not_write_activity_logs(): void
    {
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_creating_an_option_is_logged_with_its_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Bahai'])
            ->assertCreated();

        $log = ActivityLog::with('changes')->sole();

        $this->assertSame('lookups', $log->module);
        $this->assertSame('created', $log->action);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Super Administrator', $log->user_name);
        $this->assertSame('Lookup', $log->subject_type);
        $this->assertSame('Bahai', $log->subject_label);
        $this->assertSame('api', $log->platform);
        $this->assertSame('Super Administrator created Lookup "Bahai"', $log->description);
        $this->assertSame('Bahai', $log->changes->firstWhere('field', 'name')->new_value);
        $this->assertNull($log->changes->firstWhere('field', 'name')->old_value);
        $this->assertNull($log->changes->firstWhere('field', 'created_by'));
    }

    public function test_updating_logs_only_changed_fields_with_old_and_new_values(): void
    {
        $lookup = Lookup::where('type', 'bank')->where('name', 'HBL')->first();

        $this->actingAs($this->admin, 'sanctum')->putJson('/api/erp/lookups/'.$lookup->id, [
            'type' => 'bank',
            'name' => 'Habib Bank',
            'sort_order' => $lookup->sort_order,
            'is_active' => false,
        ])->assertOk();

        $log = ActivityLog::with('changes')->where('action', 'updated')->sole();

        $this->assertEqualsCanonicalizing(['name', 'is_active'], $log->changes->pluck('field')->all());
        $this->assertSame('HBL', $log->changes->firstWhere('field', 'name')->old_value);
        $this->assertSame('Habib Bank', $log->changes->firstWhere('field', 'name')->new_value);
        $this->assertSame('Yes', $log->changes->firstWhere('field', 'is_active')->old_value);
        $this->assertSame('No', $log->changes->firstWhere('field', 'is_active')->new_value);
    }

    public function test_saving_without_real_changes_writes_no_log(): void
    {
        $lookup = Lookup::where('type', 'bank')->where('name', 'HBL')->first();

        $this->actingAs($this->admin, 'sanctum')->putJson('/api/erp/lookups/'.$lookup->id, [
            'type' => 'bank',
            'name' => 'HBL',
            'sort_order' => $lookup->sort_order,
        ])->assertOk();

        $this->assertSame(0, ActivityLog::where('action', 'updated')->count());
    }

    public function test_deleting_is_logged(): void
    {
        $lookup = Lookup::where('type', 'house')->where('name', 'Red House')->first();

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/lookups/'.$lookup->id)->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'lookups',
            'action' => 'deleted',
            'subject_id' => $lookup->id,
            'subject_label' => 'Red House',
        ]);
    }

    public function test_passwords_are_never_written_to_the_log(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->put(route('admin.users.update', $teacher), [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'roles' => ['teacher'],
        ])->assertRedirect();

        $passwordChange = ActivityLog::with('changes')->where('module', 'users')->where('action', 'updated')->sole()
            ->changes->firstWhere('field', 'password');

        $this->assertSame('[hidden]', $passwordChange->old_value);
        $this->assertSame('[hidden]', $passwordChange->new_value);
        $this->assertDatabaseMissing('activity_log_changes', ['new_value' => $teacher->refresh()->password]);
    }

    public function test_web_sign_in_sign_out_and_failed_attempts_are_logged(): void
    {
        $this->post('/login', ['username' => 'admin@lms.test', 'password' => 'wrong-pass']);
        $this->post('/login', ['username' => 'admin@lms.test', 'password' => 'password']);
        $this->post('/logout');

        $actions = ActivityLog::where('module', 'auth')->orderBy('id')->pluck('action')->all();
        $failed = ActivityLog::where('action', 'login_failed')->sole();
        $login = ActivityLog::where('action', 'login')->sole();

        $this->assertSame(['login_failed', 'login', 'logout'], $actions);
        $this->assertNull($failed->user_id);
        $this->assertSame('Failed sign-in attempt for "admin@lms.test"', $failed->description);
        $this->assertSame($this->admin->id, $login->user_id);
        $this->assertSame('web', $login->platform);
    }

    public function test_api_sign_in_is_logged_against_the_user(): void
    {
        $this->postJson('/api/login', ['email' => 'admin@lms.test', 'password' => 'password', 'device_name' => 'pixel-8'])
            ->assertOk();

        $log = ActivityLog::where('action', 'login')->sole();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Super Administrator signed in via API (pixel-8)', $log->description);
    }

    public function test_activity_log_api_filters_and_shows_changes(): void
    {
        $this->post('/login', ['username' => 'nobody@lms.test', 'password' => 'wrong-pass']);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Bahai'])->assertCreated();
        $created = ActivityLog::where('action', 'created')->sole();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/activity-logs?module=lookups&action=created')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.description', 'Super Administrator created Lookup "Bahai"');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/activity-logs/'.$created->id)
            ->assertOk()
            ->assertJsonPath('data.module', 'lookups')
            ->assertJsonFragment(['field' => 'name', 'old_value' => null, 'new_value' => 'Bahai']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/activity-logs/datatable?draw=1&start=0&length=10&module=auth')
            ->assertOk()
            ->assertJsonPath('data.recordsFiltered', 1)
            ->assertJsonPath('data.data.0.action_label', 'Login Failed');
    }

    public function test_activity_log_grid_accepts_the_real_browser_request(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Bahai'])->assertCreated();

        $query = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'columns' => [
                ['data' => 'created_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'description', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'search' => ['value' => 'Bahai', 'regex' => 'false'],
            'module' => '',
            'action' => '',
            'date_from' => '',
            'date_to' => '',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/activity-logs/datatable?'.$query)
            ->assertOk()
            ->assertJsonPath('data.recordsFiltered', 1)
            ->assertJsonPath('data.data.0.module_label', 'Lookups')
            ->assertJsonPath('data.data.0.description', 'Super Administrator created Lookup "Bahai"');
    }

    public function test_activity_log_rejects_bad_date_filters(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/activity-logs?date_from=2026-10-10&date_to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to'], 'errors.fields');
    }

    public function test_activity_log_is_read_only_and_restricted(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Bahai']);
        $log = ActivityLog::sole();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/activity-logs')->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/activity-logs/'.$log->id)->assertMethodNotAllowed();
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/erp/activity-logs/'.$log->id, [])->assertMethodNotAllowed();

        $this->assertModelExists($log);
    }

    public function test_activity_log_page_renders_with_filters(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Bahai']);

        $this->actingAs($this->admin)
            ->get('/admin/activity-log')
            ->assertOk()
            ->assertSee('<option value="lookups">Lookups</option>', false)
            ->assertSee('data-datatable-url="'.route('erp.activity-logs.datatable').'"', false);
    }
}
