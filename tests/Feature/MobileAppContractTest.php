<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every endpoint the mobile app reads, called the way the app calls it, for each role: it must answer in the
 * standard envelope with the fields the app's screens use. The web pages use the same endpoints, so a change
 * that breaks this breaks both.
 */
class MobileAppContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
    }

    private function signIn(string $email): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'password', 'device_id' => "phone-{$email}", 'platform' => 'android', 'app_version' => '1.0.0'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name'], 'authorization' => ['roles', 'permissions']]])
            ->json('data.token');
    }

    /**
     * @param  array<int|string, mixed>  $structure
     * @return mixed
     */
    private function read(string $token, string $url, array $structure = [])
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('success', true);

        if ($structure !== []) {
            $response->assertJsonStructure(['data' => $structure]);
        }

        return $response->json('data');
    }

    public function test_the_app_works_before_sign_in_and_for_everyone_signed_in(): void
    {
        $this->getJson('/api/site/app-config')->assertOk()->assertJsonStructure(['data' => ['school_name', 'school_tagline', 'logo_url', 'logo_version', 'realtime', 'push_enabled']]);

        foreach (['parent@lms.test', 'student@lms.test', 'teacher@lms.test', 'admin@lms.test'] as $email) {
            $token = $this->signIn($email);
            $this->read($token, '/api/me', ['user' => ['id', 'name'], 'authorization' => ['roles', 'permissions']]);
            $this->read($token, '/api/erp/notifications/summary', ['unread', 'latest']);
            $this->read($token, '/api/erp/notifications?page=1&per_page=20', ['items', 'meta' => ['current_page', 'last_page']]);
            $this->read($token, '/api/erp/my-announcements?per_page=50', ['items']);
            $this->read($token, '/api/erp/devices', [['id', 'platform', 'device_name', 'is_current', 'last_active_at']]);
            $this->read($token, '/api/erp/my-id-cards');
            $this->read($token, '/api/erp/my-attendance-qr');
            $this->withToken($token)->putJson('/api/erp/devices/push-token', ['push_token' => "fcm-{$email}"])->assertOk();
        }
    }

    public function test_parent_and_student_screens(): void
    {
        foreach (['parent@lms.test', 'student@lms.test'] as $email) {
            $token = $this->signIn($email);
            $children = $this->read($token, '/api/erp/family/students', [['id', 'name', 'admission_no', 'status', 'class']]);
            $id = $children[0]['id'];

            $this->read($token, "/api/erp/family/students/{$id}", [
                'student' => ['id', 'name', 'admission_no', 'class', 'roll_no', 'session', 'class_teacher', 'leaving_certificate_url'],
                'timetable', 'exams', 'fees' => ['due', 'advance', 'open_challans', 'receipts'], 'library', 'transport',
            ]);
            $this->read($token, "/api/erp/attendance/students/{$id}?from=2026-10-01&to=2026-10-22", ['percentage', 'counts', 'days']);
            $this->read($token, "/api/erp/fees/students/{$id}/payment-proofs", ['pay_to' => ['methods', 'bank'], 'proofs']);
            $this->read($token, "/api/erp/exams/results/students/{$id}");
            $this->read($token, "/api/erp/diary/students/{$id}");
            $this->read($token, "/api/erp/homework?student_id={$id}&per_page=50", ['items', 'meta']);
            $this->read($token, "/api/erp/leave-requests?student_id={$id}&per_page=50", ['items', 'meta']);
            $this->read($token, '/api/erp/my-online-tests'.($email === 'parent@lms.test' ? "?student_id={$id}" : ''));
        }
    }

    public function test_teacher_screens_and_staff_chat(): void
    {
        $token = $this->signIn('teacher@lms.test');

        $this->read($token, '/api/erp/teacher/dashboard', ['teacher', 'metrics' => ['periods_today', 'students', 'sections', 'sections_marked_today', 'today_attendance_percentage'], 'today_schedule']);
        $options = $this->read($token, '/api/erp/teacher/options', ['sections' => [['id', 'name', 'class_id', 'is_class_teacher', 'subjects']], 'exams']);
        $section = $options['sections'][0]['id'];

        $this->read($token, "/api/erp/attendance/sheet?section_id={$section}&date=2026-10-22", ['date', 'holiday', 'is_sunday', 'marked', 'rows']);
        $this->read($token, "/api/erp/diary?section_id={$section}&date=2026-10-22", ['id', 'status', 'note', 'entries', 'remarks', 'students']);
        $this->read($token, "/api/erp/homework?section_id={$section}&per_page=50", ['items', 'meta']);
        $this->read($token, '/api/erp/leave-requests?status=pending&per_page=50', ['items', 'meta']);
        $this->read($token, '/api/erp/lookup-options/diary_entry_type', [['id', 'name']]);
        $this->read($token, '/api/erp/lookup-options/diary_remark_type');
        $this->read($token, '/api/erp/lookup-options/loan_type');
        $this->read($token, '/api/erp/my-hr/leave', ['balances', 'requests']);
        $this->read($token, '/api/erp/my-hr/payslips');
        $this->read($token, '/api/erp/my-hr/loans');

        $this->read($token, '/api/erp/chat/conversations');
        $this->read($token, '/api/erp/chat/unread', ['unread']);
        $people = $this->read($token, '/api/erp/chat/people', [['id', 'name']]);
        $this->app['auth']->forgetGuards();
        $chat = $this->withToken($token)->postJson('/api/erp/chat/direct', ['user_id' => $people[0]['id']])->assertCreated()->json('data');
        $this->read($token, "/api/erp/chat/conversations/{$chat['id']}/messages");
    }

    public function test_family_accounts_get_a_clean_refusal_not_a_crash_on_staff_screens(): void
    {
        $token = $this->signIn('parent@lms.test');
        $other = Student::whereNotIn('id', Student::ownIdsFor(User::where('email', 'parent@lms.test')->first()))->value('id');

        foreach (['/api/erp/teacher/options', '/api/erp/teacher/dashboard', '/api/erp/chat/conversations', '/api/erp/attendance/sheet?section_id=1&date=2026-10-22'] as $url) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($url)->assertForbidden()->assertJsonPath('success', false);
        }
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/erp/family/students/{$other}")->assertNotFound()->assertJsonPath('success', false);
        $this->app['auth']->forgetGuards();
        $this->withToken('not-a-real-token')->getJson('/api/me')->assertUnauthorized()->assertJsonPath('success', false);
    }
}
