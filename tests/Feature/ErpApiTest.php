<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_login_and_get_sanctum_token(): void
    {
        $response = $this->postJson('/api/erp/auth/login', [
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

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/erp/auth/login', [
            'email' => 'admin@lms.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Authentication failed.',
            ]);
    }

    public function test_me_endpoint_returns_user_role_permissions_and_portals(): void
    {
        $admin = User::where('email', 'admin@lms.test')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/erp/auth/me');

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
            ]);
    }

    public function test_teacher_can_access_teacher_dashboard_and_classes(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $response = $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/erp/teacher/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'teacher',
                    'metrics',
                    'today_schedule',
                ],
            ]);

        $classesResponse = $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/erp/teacher/classes');

        $classesResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'course_code',
                        'title',
                        'section',
                    ],
                ],
            ]);
    }

    public function test_student_can_access_student_dashboard_courses_and_grades(): void
    {
        $student = User::where('email', 'student@lms.test')->first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/erp/student/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'student',
                    'academic_summary',
                ],
            ]);

        $gradesResponse = $this->actingAs($student, 'sanctum')
            ->getJson('/api/erp/student/grades');

        $gradesResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'gpa',
                    'grades',
                ],
            ]);
    }

    public function test_parent_can_access_parent_dashboard_children_fees_and_notices(): void
    {
        $parent = User::where('email', 'parent@lms.test')->first();

        $dashboardResponse = $this->actingAs($parent, 'sanctum')
            ->getJson('/api/erp/parent/dashboard');

        $dashboardResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'parent',
                    'children',
                    'summary',
                    'recent_notices',
                ],
            ]);

        $childrenResponse = $this->actingAs($parent, 'sanctum')
            ->getJson('/api/erp/parent/children');

        $childrenResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'children',
                ],
            ]);

        $feesResponse = $this->actingAs($parent, 'sanctum')
            ->getJson('/api/erp/parent/fees');

        $feesResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'ledger',
                ],
            ]);

        $noticesResponse = $this->actingAs($parent, 'sanctum')
            ->getJson('/api/erp/parent/notices');

        $noticesResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'notices',
                ],
            ]);
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

        $response = $this->actingAs($coordinator, 'sanctum')
            ->getJson('/api/erp/teacher/classes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'course_code',
                        'title',
                        'section',
                    ],
                ],
            ]);
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
            ]);
    }
}
