<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->parent = User::where('email', 'parent@lms.test')->first();
    }

    private function configureFirebase(): void
    {
        $path = storage_path('framework/testing/firebase-key.json');
        @mkdir(dirname($path), 0777, true);
        // A minimal OpenSSL config so key generation works on PHP builds without one (e.g. Windows).
        $openSslConfig = ['config' => dirname($path).'/openssl.cnf'];
        file_put_contents($openSslConfig['config'], "[req]\ndistinguished_name = dn\n[dn]\n");
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, ...$openSslConfig]);
        openssl_pkey_export($key, $privateKey, null, $openSslConfig);
        file_put_contents($path, json_encode(['client_email' => 'push@school.iam.gserviceaccount.com', 'private_key' => $privateKey, 'token_uri' => 'https://oauth2.googleapis.com/token']));
        config(['services.firebase.project_id' => 'pinnacle-school', 'services.firebase.credentials' => $path]);
    }

    public function test_bell_notifications_are_pushed_to_the_users_phones_once_firebase_is_set_up(): void
    {
        $device = UserDevice::create(['user_id' => $this->parent->id, 'platform' => 'android', 'device_id' => 'pixel-7', 'push_token' => 'fcm-token-1', 'is_active' => true]);
        UserDevice::create(['user_id' => $this->parent->id, 'platform' => 'ios', 'device_id' => 'old-iphone', 'push_token' => 'fcm-token-2', 'is_active' => true]);

        app(NotificationService::class)->notify([$this->parent->id], 'payment_received', 'Fee received', 'Rs 5,000 received.');
        $this->assertFalse(NotificationLog::where('channel', 'push')->exists(), 'no push rows before Firebase is configured');

        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-access', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => fn (Request $request) => $request['message']['token'] === 'fcm-token-2'
                ? Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                : Http::response(['name' => 'projects/pinnacle-school/messages/1']),
        ]);

        app(NotificationService::class)->notify([$this->parent->id], 'payment_received', 'Fee received', 'Rs 5,000 received.');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'projects/pinnacle-school/messages:send')
            && $request->hasHeader('Authorization', 'Bearer google-access')
            && $request['message']['token'] === 'fcm-token-1'
            && $request['message']['notification']['title'] === 'Fee received');
        $this->assertSame('sent', NotificationLog::where('channel', 'push')->value('status'));
        $this->assertSame('fcm-token-1', $device->refresh()->push_token);
        $this->assertNull(UserDevice::where('device_id', 'old-iphone')->value('push_token'), 'an uninstalled app loses its token');
    }
}
