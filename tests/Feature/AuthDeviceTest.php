<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthDeviceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up seeded roles, permissions and users (usernames, phone numbers).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Sign in through the API the way the mobile app does.
     *
     * @param  array<string, mixed>  $device
     */
    private function apiLogin(string $identifier, array $device = []): string
    {
        return $this->postJson('/api/login', ['email' => $identifier, 'password' => 'password', ...$device])
            ->assertOk()
            ->json('data.token');
    }

    public function test_api_login_accepts_email_username_or_phone(): void
    {
        $this->postJson('/api/login', ['email' => 'PIA-2026-8812', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'student@lms.test');

        $this->postJson('/api/login', ['email' => '03001234567', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'parent@lms.test');

        $this->postJson('/api/login', ['email' => 'admin', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.email.0', 'Invalid email or password.');
    }

    public function test_web_login_accepts_username_and_records_last_login(): void
    {
        $this->post('/login', ['role' => 'teacher', 'username' => 'FAC-BIO-441', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->assertAuthenticatedAs($teacher);
        $this->assertNotNull($teacher->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['module' => 'auth', 'action' => 'login', 'user_id' => $teacher->id]);
    }

    public function test_disabled_account_cannot_sign_in_on_web_or_api(): void
    {
        User::where('email', 'teacher@lms.test')->update(['is_active' => false]);

        $this->post('/login', ['role' => 'teacher', 'username' => 'teacher@lms.test', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'This account has been disabled. Please contact the school office.']);
        $this->assertGuest();

        $this->postJson('/api/login', ['email' => 'FAC-BIO-441', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.email.0', 'This account has been disabled. Please contact the school office.');

        $this->assertSame(0, User::where('email', 'teacher@lms.test')->first()->tokens()->count());
    }

    public function test_public_registration_cannot_pick_a_staff_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@lms.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'admin',
        ])->assertUnprocessable()->assertJsonPath('errors.first_key', 'role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@lms.test']);
    }

    public function test_login_registers_the_device_once_per_device_id(): void
    {
        $device = ['device_id' => 'pixel-8', 'platform' => 'android', 'device_name' => 'Pixel 8', 'app_version' => '1.0.0'];

        $this->apiLogin('admin', $device);
        $this->apiLogin('admin', $device);

        $admin = User::where('email', 'admin@lms.test')->first();
        $this->assertSame(1, UserDevice::where('user_id', $admin->id)->where('device_id', 'pixel-8')->count());
        $this->assertSame('android', UserDevice::where('device_id', 'pixel-8')->value('platform'));
    }

    public function test_devices_endpoint_lists_own_devices_and_saves_push_token(): void
    {
        $token = $this->apiLogin('admin', ['device_id' => 'iphone-15', 'platform' => 'ios', 'device_name' => 'iPhone 15']);

        $this->withToken($token)->getJson('/api/erp/devices')
            ->assertOk()
            ->assertJsonPath('data.0.device_name', 'iPhone 15')
            ->assertJsonPath('data.0.platform', 'ios')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.has_push_token', false)
            ->assertJsonMissingPath('data.0.push_token');

        $this->withToken($token)->putJson('/api/erp/devices/push-token', ['push_token' => 'apns-abc'])
            ->assertOk()
            ->assertJsonPath('message', 'Push token saved.');

        $this->assertSame('apns-abc', UserDevice::where('device_id', 'iphone-15')->value('push_token'));
    }

    public function test_revoking_a_device_deletes_its_token_and_only_own_devices_can_be_revoked(): void
    {
        $phoneToken = $this->apiLogin('admin', ['device_id' => 'old-phone', 'platform' => 'android']);
        $tabletToken = $this->apiLogin('admin', ['device_id' => 'tablet', 'platform' => 'android']);
        $studentToken = $this->apiLogin('PIA-2026-8812', ['device_id' => 'student-phone', 'platform' => 'android']);
        $phone = UserDevice::where('device_id', 'old-phone')->first();

        $this->withToken($studentToken)->deleteJson('/api/erp/devices/'.$phone->id)->assertNotFound();
        $this->app['auth']->forgetGuards();

        $this->withToken($tabletToken)->deleteJson('/api/erp/devices/'.$phone->id)
            ->assertOk()
            ->assertJsonPath('message', 'Device signed out.');
        $this->app['auth']->forgetGuards();

        $this->assertFalse($phone->refresh()->is_active);
        $this->withToken($phoneToken)->getJson('/api/erp/devices')->assertUnauthorized();
        $this->assertDatabaseHas('activity_logs', ['module' => 'auth', 'action' => 'device_revoked']);
    }
}
