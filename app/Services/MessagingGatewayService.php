<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SMS and WhatsApp through the school's provider's HTTP API (most local SMS / WhatsApp gateways work this way).
 * Per channel in .env: SMS_GATEWAY_URL (or WHATSAPP_GATEWAY_URL) with {to}, {message} and {sender} placeholders,
 * optional _METHOD (GET or POST; POST sends to / message / sender as form fields), _TOKEN (Bearer) and _SENDER.
 * Without a URL the channel stays off and messages are logged as not sent.
 */
class MessagingGatewayService
{
    public function isConfigured(string $channel): bool
    {
        return in_array($channel, ['sms', 'whatsapp'], true) && filled(config("services.{$channel}.url"));
    }

    public function send(string $channel, string $phone, string $message): void
    {
        $config = config("services.{$channel}");
        $to = $this->internationalNumber($phone);
        $values = ['{to}' => $to, '{message}' => $message, '{sender}' => (string) ($config['sender'] ?? '')];
        $request = Http::timeout(15)->when(filled($config['token'] ?? null), fn ($http) => $http->withToken($config['token']));

        $response = strtoupper((string) ($config['method'] ?? 'GET')) === 'POST'
            ? $request->asForm()->post(strtr($config['url'], array_map('rawurlencode', $values)), ['to' => $to, 'message' => $message, 'sender' => $values['{sender}']])
            : $request->get(strtr($config['url'], array_map('rawurlencode', $values)));

        if (! $response->successful()) {
            throw new RuntimeException(ucfirst($channel).' gateway answered HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }
    }

    /**
     * 0300 1234567 / +92 300 1234567 → 923001234567.
     */
    public function internationalNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return str_starts_with($digits, '0') ? '92'.substr($digits, 1) : $digits;
    }
}
