<?php

namespace Tests\Feature;

use App\Models\CardTemplate;
use App\Models\Employee;
use App\Models\IdCard;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdCardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    private function grade5A(): Section
    {
        return Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 5')->where('sections.name', 'A')->select('sections.*')->first();
    }

    public function test_cards_are_issued_once_per_holder_per_session(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'section_id' => $this->grade5A()->id])
            ->assertCreated()
            ->assertJsonPath('data.issued', 3)
            ->assertJsonPath('data.skipped', 0);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student'])
            ->assertCreated()
            ->assertJsonPath('data.issued', 2)
            ->assertJsonPath('data.skipped', 3);

        $card = IdCard::where('holder_type', 'student')->first();
        $this->assertSame($card->academicSession->ends_on->toDateString(), $card->valid_until->toDateString());
        $this->assertSame(CardTemplate::where('card_type', 'student')->where('is_default', true)->value('id'), $card->card_template_id);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/id-cards/issue', ['holder_type' => 'employee'])
            ->assertCreated()
            ->assertJsonPath('data.issued', Employee::whereIn('status', Employee::CURRENT_STATUSES)->count());
    }

    public function test_printing_returns_a_pdf_and_is_logged(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'section_id' => $this->grade5A()->id]);
        $ids = IdCard::pluck('id')->all();

        $response = $this->actingAs($this->admin)->get('/api/erp/id-cards/print?'.http_build_query(['ids' => $ids]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), '/Type /Page') - substr_count($response->getContent(), '/Type /Pages'));
        $this->assertDatabaseCount('print_logs', 3);
        $this->assertSame(1, IdCard::first()->print_count);
        $this->assertDatabaseHas('activity_logs', ['module' => 'id_cards', 'action' => 'printed']);
    }

    public function test_the_qr_page_shows_status_and_a_lost_card_is_reissued(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'holder_ids' => [Student::where('first_name', 'John')->value('id')]]);
        $card = IdCard::first();

        $this->get('/verify/'.$card->qr_token)->assertOk()->assertSee('John Doe')->assertSee('Valid card')->assertDontSee('03001234567');

        $replacement = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/id-cards/{$card->id}/status", ['status' => 'lost', 'reason' => 'Lost on the bus', 'reissue' => true])
            ->assertOk()
            ->assertJsonPath('data.replacement_of_id', $card->id)
            ->json('data');

        $this->assertSame('replaced', $card->refresh()->status);
        $this->get('/verify/'.$card->qr_token)->assertOk()->assertSee('Card replaced');
        $this->assertNotSame($card->card_no, $replacement['card_no']);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/id-cards/{$card->id}/status", ['status' => 'revoked'])->assertUnprocessable();
        $this->get('/verify/not-a-real-token-1234567890')->assertNotFound();
    }

    public function test_sizes_and_items_are_limited_by_card_type(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/card-templates', ['name' => 'Wide student card', 'card_type' => 'student', 'size_key' => 'a6_landscape'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.size_key.0', 'Choose one of the sizes allowed for this card type.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/card-templates', [
                'name' => 'Annual Day Invitation', 'card_type' => 'invitation', 'size_key' => 'a6_landscape',
                'fields' => [['side' => 'front', 'field_key' => 'full_name', 'x_mm' => 1, 'y_mm' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['fields.0.field_key' => ['This item cannot be placed on this kind of card.']]);

        $invitation = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/card-templates', [
                'name' => 'Annual Day Invitation', 'card_type' => 'invitation', 'size_key' => 'a6_landscape',
                'fields' => [['side' => 'front', 'field_key' => 'custom_text', 'custom_text' => 'You are invited', 'x_mm' => 10, 'y_mm' => 30, 'font_size' => 14]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.width_mm', 148)
            ->assertJsonPath('data.orientation', 'landscape')
            ->json('data');

        $copies = $this->actingAs($this->admin)->get('/api/erp/card-templates/'.$invitation['id'].'/print?copies=5');
        $copies->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('print_logs', ['document_type' => 'card_template', 'printable_id' => $invitation['id'], 'copies' => 5]);

        $student = CardTemplate::where('card_type', 'student')->where('is_system', true)->first();
        $this->actingAs($this->admin)->get('/api/erp/card-templates/'.$student->id.'/preview')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->admin)->get('/api/erp/card-templates/'.$student->id.'/print?copies=2')->assertStatus(422);
    }

    public function test_system_templates_and_templates_with_cards_cannot_be_deleted(): void
    {
        $system = CardTemplate::where('card_type', 'employee')->where('is_system', true)->first();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/card-templates/'.$system->id)->assertUnprocessable();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/card-templates/'.$system->id, ['name' => 'Staff card', 'card_type' => 'invitation', 'size_key' => 'cr80_portrait'])
            ->assertUnprocessable();

        $custom = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/card-templates', ['name' => 'Library Card', 'card_type' => 'student', 'size_key' => 'cr80_portrait', 'is_default' => true])
            ->assertCreated()
            ->json('data');
        $this->assertSame(1, CardTemplate::where('card_type', 'student')->where('is_default', true)->count());

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'card_template_id' => $custom['id'], 'holder_ids' => [Student::first()->id]]);
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/card-templates/'.$custom['id'])->assertUnprocessable();
    }

    public function test_the_grid_does_not_expose_qr_tokens(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'holder_ids' => [Student::first()->id]]);

        $row = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/id-cards/datatable?draw=1')->assertOk()->json('data.data.0');
        $this->assertArrayNotHasKey('qr_token', $row);
    }

    public function test_card_screens_need_the_permission(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->get(route('admin.cards.index'))->assertOk()->assertSee('data-cards', false);
        $this->actingAs($this->admin)->get(route('admin.cards.templates'))->assertOk()->assertSee('data-card-templates', false);
        $this->actingAs($teacher)->get(route('admin.cards.index'))->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'employee'])->assertForbidden();
    }
}
