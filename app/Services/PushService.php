<?php

namespace App\Services;

use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging (HTTP v1) push to a user's signed-in mobile devices. Set FIREBASE_PROJECT_ID
 * and FIREBASE_CREDENTIALS (path to the service-account key file) in .env; until then push is skipped.
 * A token Firebase reports as no longer registered is cleared from the device.
 */
class PushService
{
    public function isConfigured(): bool
    {
        $credentials = config('services.firebase.credentials');

        return filled(config('services.firebase.project_id')) && filled($credentials) && is_file($credentials);
    }

    /**
     * Send to every active device of the user; returns how many devices accepted it.
     *
     * @param  array<string, string>  $data
     */
    public function sendToUser(int $userId, string $title, ?string $body, array $data = []): int
    {
        $devices = UserDevice::where('user_id', $userId)->where('is_active', true)->whereNotNull('push_token')->get(['id', 'push_token']);
        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.config('services.firebase.project_id').'/messages:send';
        $sent = 0;

        foreach ($devices as $device) {
            $response = Http::withToken($this->accessToken())->timeout(10)->post($endpoint, ['message' => [
                'token' => $device->push_token,
                'notification' => ['title' => $title, 'body' => (string) $body],
                'data' => array_map('strval', $data),
            ]]);

            if ($response->successful()) {
                $sent++;
            } elseif ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
                $device->forceFill(['push_token' => null])->save();
            } else {
                throw new RuntimeException('Push failed: '.$response->json('error.message', 'HTTP '.$response->status()));
            }
        }

        return $sent;
    }

    /**
     * OAuth token from the service-account key (a signed JWT exchanged at Google), cached for 50 minutes.
     */
    private function accessToken(): string
    {
        return Cache::remember('firebase:access-token', now()->addMinutes(50), function (): string {
            $key = json_decode((string) file_get_contents(config('services.firebase.credentials')), true);
            $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $tokenUri = $key['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $key['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'iat' => time(),
                'exp' => time() + 3600,
            ]));

            if (! openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The Firebase key could not sign the request.');
            }

            return Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ])->throw()->json('access_token');
        });
    }
}
