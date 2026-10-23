<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_login_and_get_sanctum_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@lms.test',
            'password' => 'password',
            'device_name' => 'flutter-app',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                    ],
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_admin_can_login_from_api_auth_route_and_get_sanctum_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@lms.test',
            'password' => 'password',
            'device_name' => 'mobile-app',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'authorization' => [
                        'roles',
                        'permissions',
                        'portals',
                        'device_name',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@lms.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Authentication failed.',
            ])
            ->assertJsonPath('errors.first_key', 'email')
            ->assertJsonPath('errors.fields.email.0', 'Invalid email or password.');
    }

    public function test_login_validation_response_is_returned_in_custom_keyed_format(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ])
            ->assertJsonPath('errors.first_key', 'email')
            ->assertJsonPath('errors.fields.email.0', 'The email field is required.')
            ->assertJsonPath('errors.fields.password.0', 'The password field is required.');
    }

    public function test_me_endpoint_returns_user_role_permissions_and_portals(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                    ],
                    'authorization' => [
                        'role',
                        'roles',
                        'permissions',
                        'portals',
                        'is_admin',
                        'device_name',
                        'token',
                    ],
                ],
            ]);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/erp/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'overview' => [
                        'total_users',
                        'total_admins',
                        'total_teachers',
                        'total_students',
                        'total_roles',
                    ],
                    'system_status',
                ],
            ]);
    }

    public function test_student_is_forbidden_from_admin_dashboard(): void
    {
        $student = User::where('email', 'student@lms.test')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/erp/admin/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.first_key', 'permission');
    }

    public function test_teacher_dashboard_and_classes_come_from_assignments_and_timetable(): void
    {
        $this->travelTo(now()->startOfWeek()->setTime(9, 0));
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/erp/teacher/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teacher.name', 'Sarah Jenkins')
            ->assertJsonPath('data.metrics.sections', 1)
            ->assertJsonPath('data.metrics.students', Enrollment::where('section_id', TeacherAssignment::value('section_id'))->where('status', 'active')->count())
            ->assertJsonStructure(['data' => ['teacher', 'metrics', 'today_schedule' => [['period', 'starts_at', 'subject', 'section']]]]);

        $this->getJson('/api/erp/teacher/classes')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'course_code', 'title', 'section', 'students', 'schedule']]])
            ->assertJsonPath('data.0.title', 'English');
    }

    public function test_student_dashboard_and_results_come_from_the_student_record(): void
    {
        $student = User::where('email', 'student@lms.test')->first();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/erp/student/dashboard')
            ->assertOk()
            ->assertJsonPath('data.student.name', 'John Doe')
            ->assertJsonPath('data.student.class', 'Grade 5')
            ->assertJsonStructure(['data' => ['student', 'academic_summary' => ['attendance_percentage', 'latest_result', 'fee_balance'], 'upcoming_exams']]);

        $this->getJson('/api/erp/student/grades')
            ->assertOk()
            ->assertJsonStructure(['data' => ['latest', 'results']]);
    }

    public function test_parent_sees_only_their_own_children_fees_and_notices(): void
    {
        $parent = User::where('email', 'parent@lms.test')->first();
        $childIds = Guardian::where('user_id', $parent->id)->first()->students()->pluck('students.id');

        $this->actingAs($parent, 'sanctum')
            ->getJson('/api/erp/parent/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.children', $childIds->count())
            ->assertJsonPath('data.children.0.name', 'John Doe')
            ->assertJsonStructure(['data' => ['parent', 'children', 'summary', 'recent_notices']]);

        $this->getJson('/api/erp/parent/children')->assertOk()->assertJsonCount($childIds->count(), 'data.children');

        $fees = $this->getJson('/api/erp/parent/fees')->assertOk()->assertJsonStructure(['data' => ['summary', 'ledger', 'meta']])->json('data');
        $this->assertSame(FeeInvoice::whereIn('student_id', $childIds)->where('status', '!=', 'cancelled')->count(), $fees['meta']['total']);

        $this->getJson('/api/erp/parent/notices')->assertOk()->assertJsonStructure(['data' => ['notices']]);
    }

    public function test_custom_role_with_permission_can_access_teacher_classes_route(): void
    {
        $coordinatorRole = Role::create([
            'name' => 'Academic Coordinator',
            'slug' => 'academic-coordinator',
            'description' => 'Can supervise teacher workflows.',
        ]);
        $coordinatorRole->syncPermissions(['manage-classes']);

        $coordinator = User::create([
            'name' => 'Coordinator User',
            'email' => 'coordinator@lms.test',
            'password' => 'password',
        ]);
        $coordinator->syncRoles(['academic-coordinator']);

        // Allowed in, but with no employee record there are no classes of their own.
        $this->actingAs($coordinator, 'sanctum')
            ->getJson('/api/erp/teacher/classes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_admin_can_create_dynamic_custom_role_and_assign_to_user(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        // 1. Create a dynamic new role (e.g. Accountant)
        $roleResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/erp/roles', [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Handles student fee vouchers and invoices',
            ]);

        $roleResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Accountant',
                    'slug' => 'accountant',
                ],
            ]);

        $accountantRoleId = $roleResponse->json('data.id');

        // 2. Create new user
        $newUserResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/erp/users', [
                'name' => 'David Finance',
                'email' => 'david@lms.test',
                'password' => 'secret123',
                'roles' => ['accountant'],
            ]);

        $newUserResponse->assertStatus(201);
        $newUserId = $newUserResponse->json('data.id');

        $newUser = User::find($newUserId);
        $this->assertTrue($newUser->hasRole('accountant'));
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/erp/roles/{$adminRole->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.first_key', 'role')
            ->assertJsonPath('errors.fields.role.0', 'System roles cannot be deleted.');
    }
}
