<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<int, string>
     */
    private function classNames(array $extra = []): array
    {
        $query = http_build_query([
            'draw' => 1, 'start' => 0, 'length' => 50,
            'columns' => [
                ['data' => 'name', 'searchable' => 'true'],
                ['data' => 'wing_name', 'searchable' => 'false'],
                ['data' => 'next_class_name', 'searchable' => 'false'],
                ['data' => 'sections_count', 'searchable' => 'false'],
                ['data' => 'is_active', 'searchable' => 'false'],
            ],
        ] + $extra);

        return collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/classes/datatable?'.$query)->assertOk()->json('data.data'))->pluck('name')->all();
    }

    public function test_grid_tells_which_columns_and_dates_it_can_filter(): void
    {
        $payload = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/classes/datatable?'.http_build_query(['draw' => 1, 'columns' => [['data' => 'name'], ['data' => 'wing_name'], ['data' => 'sections_count']]]))
            ->assertOk()
            ->json('data');

        $this->assertSame('created_at', $payload['date_column']);
        $this->assertSame(['name', 'wing_name'], $payload['column_search']);
    }

    public function test_column_search_filters_real_relation_and_self_relation_columns(): void
    {
        $this->assertSame(['Grade 5'], $this->classNames(['col_search' => ['name' => 'Grade 5']]));
        $this->assertSame(['Grade 6', 'Grade 7', 'Grade 8'], $this->classNames(['col_search' => ['wing_name' => 'Middle']]));
        $this->assertSame(['Grade 8'], $this->classNames(['col_search' => ['next_class_name' => 'Grade 9']]));
        $this->assertCount(SchoolClass::where('is_active', true)->count(), $this->classNames(['col_search' => ['is_active' => 'yes']]));
    }

    public function test_unknown_columns_are_ignored_instead_of_breaking_the_query(): void
    {
        $this->assertCount(SchoolClass::count(), $this->classNames(['col_search' => ['sections_count' => '2', 'made_up' => 'x']]));
    }

    public function test_date_range_limits_rows_by_record_date(): void
    {
        SchoolClass::where('name', 'Grade 5')->update(['created_at' => '2020-01-15 10:00:00']);

        $this->assertSame(['Grade 5'], $this->classNames(['date_from' => '2020-01-01', 'date_to' => '2020-01-31']));
        $this->assertNotContains('Grade 5', $this->classNames(['date_from' => '2021-01-01']));
    }

    public function test_the_same_grid_endpoint_gives_mobile_apps_a_paginated_list(): void
    {
        $total = SchoolClass::count();

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/classes/datatable?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total', $total)
            ->assertJsonPath('data.meta.last_page', (int) ceil($total / 5))
            ->assertJsonStructure(['data' => ['items' => [['id', 'name', 'wing_name', 'sections_count']]]]);
        $this->assertCount(min(5, $total - 5), $response->json('data.items'));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/classes/datatable?search=Grade+10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', 'Grade 10');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/classes/datatable?draw=1&start=0&length=5')
            ->assertOk()
            ->assertJsonPath('data.recordsTotal', $total);
    }
}
