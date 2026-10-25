<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Campus $main;

    /**
     * Set up seeded users, all assigned to the main campus.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->main = Campus::where('code', 'MAIN')->first();
    }

    public function test_admin_can_create_and_update_a_campus(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/campuses', [
            'name' => 'Gulberg Branch',
            'code' => 'GLB',
            'city' => 'Lahore',
            'is_active' => true,
        ]);

        $created->assertCreated()->assertJsonPath('data.code', 'GLB')->assertJsonPath('data.users_count', 0);
        $id = $created->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/campuses/'.$id, ['name' => 'Gulberg Campus', 'code' => 'GLB', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('activity_logs', ['module' => 'campuses', 'action' => 'updated', 'subject_id' => $id]);
    }

    public function test_code_is_uppercased_unique_and_limited_to_safe_characters(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/campuses', ['name' => 'Copy', 'code' => ' main '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code'], 'errors.fields');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/campuses', ['name' => 'Spaces', 'code' => 'GL B'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code'], 'errors.fields');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/campuses', ['name' => 'Lower', 'code' => 'glb-2'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'GLB-2');
    }

    public function test_last_active_campus_cannot_be_deactivated_or_deleted(): void
    {
        User::query()->update(['campus_id' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/campuses/'.$this->main->id, ['name' => $this->main->name, 'code' => 'MAIN', 'is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.is_active.0', 'At least one campus must stay active.');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/campuses/'.$this->main->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.campus.0', 'The last active campus cannot be deleted.');

        $this->assertTrue($this->main->refresh()->is_active);
        $this->assertNotSoftDeleted($this->main);
    }

    public function test_campus_with_users_cannot_be_deleted_until_they_move(): void
    {
        Campus::factory()->create(['code' => 'NEW']);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/campuses/'.$this->main->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.campus.0', 'This campus still has 5 users. Move them to another campus first.');

        User::query()->update(['campus_id' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/campuses/'.$this->main->id)
            ->assertOk()
            ->assertJsonPath('message', 'Campus deleted.');

        $this->assertSoftDeleted($this->main);
    }

    public function test_datatable_counts_users_and_filters_status(): void
    {
        Campus::factory()->inactive()->create(['name' => 'Old Town', 'code' => 'OLD']);

        $rows = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/campuses/datatable?draw=1&is_active=1')->json('data.data');

        $this->assertSame(['MAIN'], array_column($rows, 'code'));
        $this->assertSame(5, $rows[0]['users_count']);
    }

    public function test_campuses_require_permission(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/campuses')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/campuses', ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }

    public function test_each_campus_sees_only_its_own_records_and_head_office_can_pick_one(): void
    {
        $branch = Campus::factory()->create(['name' => 'Gulberg Branch', 'code' => 'GLB']);
        $office = Role::create(['name' => 'Campus Office', 'slug' => 'campus-office']);
        $office->permissions()->attach(Permission::where('slug', 'manage-students')->value('id'));
        $branchStaff = User::factory()->create(['campus_id' => $branch->id]);
        $mainStaff = User::factory()->create(['campus_id' => $this->main->id]);
        $branchStaff->roles()->attach($office->id);
        $mainStaff->roles()->attach($office->id);

        $this->actingAs($branchStaff);
        $student = Student::factory()->create(['first_name' => 'Branchkid']);
        $this->assertSame($branch->id, Student::withoutGlobalScopes()->find($student->id)->campus_id);

        $this->actingAs($branchStaff, 'sanctum')->getJson("/api/erp/students/{$student->id}")->assertOk();
        $this->actingAs($mainStaff, 'sanctum')->getJson("/api/erp/students/{$student->id}")->assertNotFound();
        $this->actingAs($mainStaff, 'sanctum')->getJson('/api/erp/students/datatable?search=Branchkid')->assertOk()->assertJsonPath('data.meta.total', 0);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/students/datatable?search=Branchkid')->assertJsonPath('data.meta.total', 1);
        $this->actingAs($this->admin, 'sanctum')->withHeader('X-Campus-Id', (string) $this->main->id)->getJson('/api/erp/students/datatable?search=Branchkid')->assertJsonPath('data.meta.total', 0);
        $this->actingAs($this->admin, 'sanctum')->withHeader('X-Campus-Id', (string) $branch->id)->getJson('/api/erp/students/datatable?search=Branchkid')->assertJsonPath('data.meta.total', 1);

        // Branch staff cannot widen their view with the header.
        $this->actingAs($mainStaff, 'sanctum')->withHeader('X-Campus-Id', (string) $branch->id)->getJson("/api/erp/students/{$student->id}")->assertNotFound();

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('All campuses');
    }
}
