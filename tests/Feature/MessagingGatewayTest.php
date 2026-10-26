<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->parent = User::where('email', 'parent@lms.test')->first();
        $this->parent->update(['phone' => '0300 1234567']);
    }

    private function sendPaymentMessage(): void
    {
        app(NotificationService::class)->notifyEvent('payment_received', [$this->parent->id], ['student_name' => 'John Doe', 'receipt_no' => 'RCT-1', 'amount' => 'Rs 5,000', 'date' => '22 Oct 2026']);
    }

    public function test_sms_waits_for_a_gateway_and_then_goes_through_the_providers_http_api(): void
    {
        Http::fake(['sms.example.pk/*' => Http::response('OK')]);

        $this->sendPaymentMessage();
        $this->assertSame(['failed', 'No sms provider is configured yet.'], [NotificationLog::where('channel', 'sms')->value('status'), NotificationLog::where('channel', 'sms')->value('error')]);
        Http::assertNothingSent();

        config(['services.sms.url' => 'https://sms.example.pk/send?to={to}&text={message}&from={sender}', 'services.sms.sender' => 'PINNACLE']);
        $this->sendPaymentMessage();

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://sms.example.pk/send?to=923001234567&text=')
            && str_contains(urldecode($request->url()), 'received Rs 5,000 for John Doe') && str_contains($request->url(), 'from=PINNACLE'));
        $this->assertSame('sent', NotificationLog::where('channel', 'sms')->latest('id')->value('status'));
    }

    public function test_whatsapp_posts_form_fields_and_a_failing_provider_is_logged(): void
    {
        config(['services.whatsapp.url' => 'https://wa.example.pk/api/messages', 'services.whatsapp.token' => 'secret-token']);
        Http::fake(['wa.example.pk/*' => Http::sequence()->push(['id' => 'm1'])->push('Quota exceeded', 429)]);

        app(NotificationService::class)->notify([$this->parent->id], 'notice', 'School closed', 'School is closed tomorrow.', null, null, false, ['whatsapp']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['to'] === '923001234567' && $request['message'] === 'School is closed tomorrow.' && $request->hasHeader('Authorization', 'Bearer secret-token'));
        $this->assertSame('sent', NotificationLog::where('channel', 'whatsapp')->value('status'));

        app(NotificationService::class)->notify([$this->parent->id], 'notice', 'School closed', 'Second message.', null, null, false, ['whatsapp']);
        $failed = NotificationLog::where('channel', 'whatsapp')->latest('id')->first();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('429', $failed->error);
    }
}
