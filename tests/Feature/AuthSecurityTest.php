<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
    }

    public function test_repeated_wrong_passwords_are_slowed_down_on_the_api_and_the_web_form(): void
    {
        $this->freezeTime();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'FAC-BIO-441', 'password' => 'wrong-'.$attempt])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'FAC-BIO-441', 'password' => 'password'])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Too many sign-in attempts.'));

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['username' => 'admin', 'password' => 'wrong-'.$attempt]);
        }

        $this->from('/login')->post('/login', ['username' => 'admin', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'Too many sign-in attempts. Please try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_disabling_an_account_ends_its_web_session_and_refuses_its_token(): void
    {
        $token = $this->postJson('/api/login', ['email' => 'FAC-BIO-441', 'password' => 'password'])->json('data.token');
        $this->actingAs($this->teacher)->get('/dashboard')->assertOk();

        $this->teacher->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_a_token_left_unused_for_the_idle_days_stops_working(): void
    {
        $token = $this->postJson('/api/login', ['email' => 'FAC-BIO-441', 'password' => 'password'])->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/me')->assertOk();

        $this->travel(59)->days();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/me')->assertOk();

        $this->travel(61)->days();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_forgot_password_emails_a_link_without_revealing_which_accounts_exist(): void
    {
        Notification::fake();

        $known = $this->postJson('/api/forgot-password', ['identifier' => 'FAC-BIO-441'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/forgot-password', ['identifier' => 'nobody-here'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($this->teacher, ResetPassword::class);
        Notification::assertCount(1);
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
    }

    public function test_a_reset_link_sets_the_new_password_and_signs_out_every_device(): void
    {
        $this->postJson('/api/login', ['email' => 'FAC-BIO-441', 'password' => 'password'])->assertOk();
        $token = Password::broker()->createToken($this->teacher);
        $this->get(route('password.reset', ['token' => $token, 'email' => $this->teacher->email]))->assertOk()->assertSee('Save new password');

        $this->postJson('/api/reset-password', ['token' => 'wrong', 'email' => $this->teacher->email, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.token.0', 'This reset link is invalid or has expired. Please ask for a new one.');

        $this->postJson('/api/reset-password', ['token' => $token, 'email' => $this->teacher->email, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])->assertOk();

        $this->assertTrue(Hash::check('new-secret-1', $this->teacher->refresh()->password));
        $this->assertSame(0, $this->teacher->tokens()->count());
        $this->assertDatabaseHas('activity_logs', ['module' => 'auth', 'action' => 'password_reset', 'user_id' => $this->teacher->id]);
    }
}
