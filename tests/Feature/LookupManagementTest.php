<?php

namespace Tests\Feature;

use App\Models\Lookup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    /**
     * Set up seeded roles, permissions, users, and lookup master data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
    }

    public function test_guest_gets_unauthenticated_envelope(): void
    {
        $this->getJson('/api/erp/lookups')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_user_without_permission_cannot_manage_lookups(): void
    {
        $lookup = Lookup::where('type', 'religion')->first();

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/lookups')->assertForbidden();
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Bahai'])->assertForbidden();
        $this->actingAs($this->teacher, 'sanctum')->deleteJson('/api/erp/lookups/'.$lookup->id)->assertForbidden();

        $this->assertNotSoftDeleted($lookup);
        $this->assertDatabaseMissing('lookups', ['name' => 'Bahai']);
    }

    public function test_any_signed_in_user_can_read_active_options_in_order(): void
    {
        Lookup::where('type', 'religion')->where('name', 'Sikhism')->update(['is_active' => false]);

        $response = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/lookup-options/religion');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['Islam', 'Christianity', 'Hinduism', 'Other'], array_column($response->json('data'), 'name'));
    }

    public function test_index_returns_paginated_envelope_filtered_by_type_and_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/lookups?type=blood_group&per_page=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 8)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.items.0.name', 'A+')
            ->assertJsonPath('data.items.0.type_label', 'Blood Group');

        Lookup::where('type', 'gender')->where('name', 'Female')->update(['is_active' => false]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups?type=gender&is_active=0')
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', 'Female');
    }

    public function test_datatable_endpoint_wraps_yajra_payload_in_envelope(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups/datatable?draw=3&start=0&length=10&type=bank');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.draw', 3)
            ->assertJsonPath('data.recordsTotal', 10)
            ->assertJsonPath('data.data.0.type_label', 'Bank');
        $this->assertCount(10, $response->json('data.data'));
    }

    public function test_datatable_accepts_the_real_browser_request_with_global_search_and_ordering(): void
    {
        $browserRequest = fn (string $search): string => http_build_query([
            'draw' => 2,
            'start' => 0,
            'length' => 15,
            'columns' => [
                ['data' => 'name', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'type', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'sort_order', 'name' => '', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ],
            'order' => [['column' => 2, 'dir' => 'desc', 'name' => '']],
            'search' => ['value' => $search, 'regex' => 'false'],
            'type' => 'bank',
            'is_active' => '',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups/datatable?'.$browserRequest(''))
            ->assertOk()
            ->assertJsonPath('data.draw', 2)
            ->assertJsonPath('data.recordsTotal', 10)
            ->assertJsonPath('data.recordsFiltered', 10)
            ->assertJsonPath('data.data.0.name', 'Standard Chartered');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups/datatable?'.$browserRequest('Allied'))
            ->assertOk()
            ->assertJsonPath('data.recordsFiltered', 1)
            ->assertJsonPath('data.data.0.name', 'Allied Bank');
    }

    public function test_datatable_returns_raw_values_for_the_browser_to_escape_once(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups/datatable?draw=1&start=0&length=20&type=qualification_level')
            ->assertOk()
            ->assertJsonFragment(['name' => "Bachelor's"]);
    }

    public function test_types_endpoint_lists_every_list_with_counts(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/lookups/types');

        $bloodGroup = collect($response->json('data'))->firstWhere('type', 'blood_group');

        $response->assertOk();
        $this->assertSame(['type' => 'blood_group', 'label' => 'Blood Group', 'total' => 8, 'active' => 8], $bloodGroup);
    }

    public function test_admin_can_create_an_option(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/lookups', [
            'type' => '  Religion ',
            'name' => '  Bahai ',
            'code' => 'BH',
            'sort_order' => 55,
        ]);

        $created = Lookup::where('type', 'religion')->where('name', 'Bahai')->first();

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Option created.')
            ->assertJsonPath('data.name', 'Bahai')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_system', false);
        $this->assertSame('BH', $created->code);
        $this->assertSame(55, $created->sort_order);
        $this->assertSame($this->admin->id, $created->created_by);
    }

    public function test_create_validates_required_fields_and_list_key_format(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/lookups', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name'], 'errors.fields');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/lookups', ['type' => '9 bad-key', 'name' => 'Test'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.type.0', 'The list key may only use lowercase letters, numbers and underscores, starting with a letter.');
    }

    public function test_option_names_are_unique_per_list_only(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Islam'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.name.0', 'This option already exists in the selected list.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/lookups', ['type' => 'house', 'name' => 'Islam'])
            ->assertCreated();
    }

    public function test_a_deleted_option_name_can_be_used_again(): void
    {
        $other = Lookup::where('type', 'religion')->where('name', 'Other')->first();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/lookups/'.$other->id)->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/lookups', ['type' => 'religion', 'name' => 'Other'])
            ->assertCreated();
    }

    public function test_show_returns_option_or_not_found(): void
    {
        $lookup = Lookup::where('type', 'bank')->where('name', 'HBL')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups/'.$lookup->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'HBL');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/lookups/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_update_an_option(): void
    {
        $lookup = Lookup::where('type', 'bank')->where('name', 'HBL')->first();

        $response = $this->actingAs($this->admin, 'sanctum')->putJson('/api/erp/lookups/'.$lookup->id, [
            'type' => 'bank',
            'name' => 'Habib Bank Limited',
            'code' => 'HBL',
            'sort_order' => 5,
            'is_active' => false,
        ]);

        $lookup->refresh();

        $response->assertOk()->assertJsonPath('message', 'Option updated.');
        $this->assertSame('Habib Bank Limited', $lookup->name);
        $this->assertFalse($lookup->is_active);
        $this->assertSame($this->admin->id, $lookup->updated_by);
    }

    public function test_built_in_options_stay_in_their_list_and_cannot_be_deleted(): void
    {
        $male = Lookup::where('type', 'gender')->where('name', 'Male')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/lookups/'.$male->id, ['type' => 'religion', 'name' => 'Male'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.type.0', 'Built-in options cannot be moved to another list.');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/lookups/'.$male->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.lookup.0', 'Built-in options cannot be deleted. Deactivate it instead.');

        $this->assertNotSoftDeleted($male);
        $this->assertSame('gender', $male->refresh()->type);
    }

    public function test_admin_can_delete_an_option(): void
    {
        $lookup = Lookup::factory()->create(['type' => 'house', 'name' => 'Purple House']);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/lookups/'.$lookup->id)
            ->assertOk()
            ->assertJsonPath('message', 'Option deleted.');

        $this->assertSoftDeleted($lookup);
    }

    public function test_lookups_page_renders_for_admin_only(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/lookups')
            ->assertOk()
            ->assertSee('data-datatable-url="'.route('erp.lookups.datatable').'"', false)
            ->assertSee('Blood Group');

        $this->actingAs($this->teacher)->get('/admin/lookups')->assertForbidden();
    }

    public function test_admin_web_session_can_call_the_api(): void
    {
        $this->actingAs($this->admin)
            ->withHeaders(['Referer' => 'http://127.0.0.1:8000/admin/lookups', 'Origin' => 'http://127.0.0.1:8000'])
            ->getJson('/api/erp/lookups/types')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
