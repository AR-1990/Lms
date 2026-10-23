<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Services\FeeInvoiceService;
use App\Services\StudentRecordService;
use App\Services\TransportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->student = Student::where('first_name', 'John')->where('last_name', 'Doe')->first();
        $this->actingAs($this->admin, 'sanctum');
    }

    private function stop(string $name): TransportStop
    {
        return TransportStop::where('name', $name)->firstOrFail();
    }

    private function ride(string $stop, string $from = '2026-10-01'): int
    {
        return $this->postJson('/api/erp/student-transport', ['student_id' => $this->student->id, 'transport_stop_id' => $this->stop($stop)->id, 'from_date' => $from])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_a_route_is_saved_with_its_stops_in_order(): void
    {
        $vehicle = Vehicle::where('registration_no', 'LEA-4512')->first();

        $route = $this->postJson('/api/erp/transport-routes', [
            'name' => 'Route 3 — DHA', 'vehicle_id' => $vehicle->id, 'monthly_fee' => 500000,
            'stops' => [['name' => 'Phase 5', 'pickup_time' => '06:50', 'drop_time' => '14:20'], ['name' => 'Phase 6', 'pickup_time' => '07:00', 'fee_override' => 550000]],
        ])->assertCreated()->json('data');

        $this->assertSame(['Phase 5', 'Phase 6'], array_column($route['stops'], 'name'));
        $this->assertSame('06:50', $route['stops'][0]['pickup_time']);

        $this->putJson("/api/erp/transport-routes/{$route['id']}", ['name' => 'Route 3 — DHA', 'monthly_fee' => 500000, 'stops' => [['id' => $route['stops'][1]['id'], 'name' => 'Phase 6']]])
            ->assertOk()
            ->assertJsonCount(1, 'data.stops');
        $this->assertSoftDeleted('transport_stops', ['id' => $route['stops'][0]['id']]);
    }

    public function test_riders_are_charged_on_the_monthly_challan_and_moving_ends_the_old_ride(): void
    {
        $this->ride('Model Town Link Road');
        $invoice = app(FeeInvoiceService::class)->monthly($this->student->currentEnrollment, Carbon::parse('2026-11-01'));
        $line = $invoice->items()->where('description', 'like', 'Transport%')->first();

        $this->assertNotNull($line);
        $this->assertSame(380000, (int) $line->amount);

        $this->ride('Emporium Mall', '2026-11-10');
        $this->assertSame(1, StudentTransport::where('student_id', $this->student->id)->where('status', 'active')->count());
        $this->assertSame('2026-11-09', StudentTransport::where('student_id', $this->student->id)->where('status', 'ended')->first()->to_date->toDateString());
    }

    public function test_a_full_bus_refuses_more_riders_and_a_stop_with_riders_cannot_be_removed(): void
    {
        $route = TransportRoute::where('code', 'R2')->first();
        Vehicle::whereKey($route->vehicle_id)->update(['capacity' => 1]);
        $other = Student::whereKeyNot($this->student->id)->whereHas('currentEnrollment')->first();

        $this->ride('Expo Centre');
        $this->postJson('/api/erp/student-transport', ['student_id' => $other->id, 'transport_stop_id' => $this->stop('Township Market')->id, 'from_date' => '2026-10-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transport_stop_id', 'errors.fields');

        $details = $this->getJson("/api/erp/transport-routes/{$route->id}")->json('data');
        $this->putJson("/api/erp/transport-routes/{$route->id}", ['name' => $route->name, 'stops' => collect($details['stops'])->reject(fn (array $stop): bool => $stop['name'] === 'Expo Centre')->map(fn (array $stop): array => ['id' => $stop['id'], 'name' => $stop['name']])->values()->all()])
            ->assertUnprocessable();
    }

    public function test_students_and_parents_see_their_bus_and_leaving_school_ends_the_ride(): void
    {
        $this->ride('Liberty Chowk');

        $this->actingAs(User::where('email', 'student@lms.test')->first(), 'sanctum')
            ->getJson('/api/erp/my-bus')->assertOk()->assertJsonPath('data.0.bus.stop', 'Liberty Chowk')->assertJsonPath('data.0.bus.pickup_time', '07:15');
        $this->actingAs(User::where('email', 'parent@lms.test')->first(), 'sanctum')
            ->getJson('/api/erp/parent/dashboard')->assertOk()->assertJsonPath('data.children.0.bus.route', 'Route 1 — Gulberg');

        app(StudentRecordService::class)->changeStatus($this->student, 'withdrawn', '2026-10-20', null, 'Moved city');
        $this->assertSame('ended', StudentTransport::where('student_id', $this->student->id)->first()->status);
    }

    public function test_route_sheet_and_paper_expiry_alerts(): void
    {
        $this->ride('Main Boulevard');
        $route = TransportRoute::where('code', 'R1')->first();

        $this->get("/api/erp/transport-routes/{$route->id}/sheet")->assertOk()->assertHeader('content-type', 'application/pdf');

        Vehicle::where('registration_no', 'LEA-4512')->update(['insurance_expiry' => now()->addDays(7)->toDateString()]);
        $this->assertSame(1, app(TransportService::class)->sendExpiryAlerts());
        $this->assertTrue(UserNotification::where('user_id', $this->admin->id)->where('event_key', 'vehicle_papers')->exists());
    }

    public function test_staff_without_transport_permission_are_kept_out(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/transport-routes/datatable?draw=1')->assertForbidden()->assertJsonPath('success', false);
        $this->actingAs($this->admin)->get(route('admin.transport.routes'))->assertOk();
        $this->actingAs($teacher)->get(route('admin.transport.routes'))->assertForbidden();
    }
}
