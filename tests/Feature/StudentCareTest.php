<?php

namespace Tests\Feature;

use App\Models\DisciplineRecord;
use App\Models\Lookup;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_certificates_and_what_parents_see(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@lms.test')->first();
        $parent = User::where('email', 'parent@lms.test')->first();
        $john = Student::where('first_name', 'John')->first();
        $this->actingAs($admin, 'sanctum');

        $this->postJson("/api/erp/students/{$john->id}/records/medical", ['condition_id' => Lookup::where('type', 'medical_condition')->where('name', 'Asthma')->value('id'), 'allergies' => 'Dust', 'height_cm' => 132.5, 'checked_on' => '2026-10-01'])->assertCreated();
        $discipline = $this->postJson("/api/erp/students/{$john->id}/records/discipline", ['incident_date' => '2026-10-21', 'category_id' => Lookup::where('type', 'discipline_category')->value('id'), 'description' => 'Late three days running', 'notify_parents' => true])
            ->assertCreated()
            ->assertJsonPath('data.parent_informed', true)
            ->json('data.id');
        $this->postJson("/api/erp/students/{$john->id}/records/achievements", ['title' => 'First in spelling bee', 'level' => 'school'])->assertCreated();
        $this->postJson("/api/erp/students/{$john->id}/records/discipline", ['category_id' => 1])->assertUnprocessable()->assertJsonPath('errors.first_key', 'incident_date');

        $this->assertTrue(UserNotification::where('user_id', $parent->id)->where('event_key', 'discipline_recorded')->exists());

        $this->putJson("/api/erp/student-records/discipline/{$discipline}", ['incident_date' => '2026-10-21', 'category_id' => Lookup::where('type', 'discipline_category')->value('id'), 'description' => 'Late twice', 'action_taken' => 'Warned'])->assertOk()->assertJsonPath('data.action_taken', 'Warned');

        $records = $this->getJson("/api/erp/students/{$john->id}/records")->assertOk();
        $this->assertCount(1, $records->json('data.medical'));
        $this->assertCount(1, $records->json('data.discipline'));

        $certificate = $this->postJson("/api/erp/students/{$john->id}/certificates", ['certificate_type' => 'bonafide', 'purpose' => 'passport'])->assertCreated()->json('data');
        $this->assertStringStartsWith('CERT-', $certificate['certificate_no']);
        $this->get($certificate['pdf_url'])->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->postJson("/api/erp/students/{$john->id}/certificates", ['certificate_type' => 'character', 'conduct_id' => Lookup::where('type', 'conduct')->value('id')])->assertCreated();

        $this->deleteJson("/api/erp/student-records/discipline/{$discipline}")->assertOk();
        $this->assertSoftDeleted('discipline_records', ['id' => $discipline]);
        $this->assertNotNull(DisciplineRecord::withTrashed()->find($discipline));

        $family = $this->actingAs($parent, 'sanctum')->getJson("/api/erp/students/{$john->id}/records")->assertOk();
        $this->assertSame([], $family->json('data.medical'));
        $this->assertCount(1, $family->json('data.achievements'));
        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/family/students/{$john->id}")->assertJsonPath('data.records.achievements.0.title', 'First in spelling bee');
        $this->actingAs($parent, 'sanctum')->postJson("/api/erp/students/{$john->id}/records/achievements", ['title' => 'X'])->assertForbidden();

        $this->actingAs($admin)->get("/admin/students/{$john->id}")->assertOk()->assertSee('Health, discipline &amp; achievements', false);
    }
}
