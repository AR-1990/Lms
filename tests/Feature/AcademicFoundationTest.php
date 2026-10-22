<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Lookup;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicSession $session;

    /**
     * Set up seeded users and the seeded 2026-27 academic setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->session = AcademicSession::where('is_current', true)->first();
    }

    public function test_seeders_build_the_class_ladder_curriculum_and_bell_schedule(): void
    {
        $prep = SchoolClass::where('name', 'Prep')->first();

        $this->assertSame('Grade 1', $prep->nextClass->name);
        $this->assertSame('Early Years', $prep->wing->name);
        $this->assertNull(SchoolClass::where('name', 'Grade 10')->value('next_class_id'));
        $this->assertSame(26, Section::where('academic_session_id', $this->session->id)->count());
        $this->assertTrue(ClassSubject::where('class_id', $prep->id)->exists());
        $this->assertSame(10, Period::count());
    }

    public function test_academic_screens_render_for_admin_and_are_forbidden_to_teachers(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        foreach (['sessions', 'classes', 'sections', 'subjects', 'curriculum', 'periods'] as $screen) {
            $this->actingAs($this->admin)
                ->get(route('admin.academics.'.$screen))
                ->assertOk()
                ->assertSee('data-academics="'.$screen.'"', false)
                ->assertSee('js/admin-academics.js', false);

            $this->actingAs($teacher)->get(route('admin.academics.'.$screen))->assertForbidden();
        }

        $this->actingAs($this->admin)
            ->get(route('admin.academics.sections'))
            ->assertSee('<option value="'.$this->session->id.'" selected>2026-27 (current)</option>', false)
            ->assertSee(route('admin.academics.curriculum'), false);
    }

    public function test_academics_require_permission_but_options_are_open_to_signed_in_users(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/classes')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/subjects', ['name' => 'Music'])->assertForbidden();

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/erp/academics/options')
            ->assertOk()
            ->assertJsonPath('data.current_session_id', $this->session->id)
            ->assertJsonPath('data.classes.0.name', 'Playgroup');

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/periods')->assertOk()->assertJsonPath('data.0.name', 'Assembly');
    }

    public function test_only_one_session_is_current_and_dates_cannot_overlap(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/academic-sessions', ['name' => '2026-B', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.starts_on.0', 'These dates overlap the 2026-27 session.');

        $next = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/academic-sessions', ['name' => '2027-28', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31', 'is_current' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_current', true)
            ->assertJsonPath('data.admissions_open', false);

        $this->assertFalse($this->session->refresh()->is_current);
        $this->assertSame(1, AcademicSession::where('is_current', true)->count());

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/academic-sessions/'.$next->json('data.id'), ['name' => '2027-28', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31', 'is_current' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'is_current');
    }

    public function test_session_in_use_or_current_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/academic-sessions/'.$this->session->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.session.0', 'The current session cannot be deleted.');

        $old = AcademicSession::factory()->create(['name' => '2020-21', 'starts_on' => '2020-08-01', 'ends_on' => '2021-07-31']);
        Section::factory()->create(['academic_session_id' => $old->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/academic-sessions/'.$old->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.session.0', 'This session already has sections or curriculum. Remove them first.');
    }

    public function test_class_promotion_rules_and_delete_cleanup(): void
    {
        $wing = Lookup::where('type', 'wing')->where('name', 'Senior School')->first();
        $created = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/classes', ['name' => 'O Level I', 'code' => 'ol-1', 'level_order' => 140, 'wing_id' => $wing->id])
            ->assertCreated()
            ->assertJsonPath('data.code', 'OL-1')
            ->assertJsonPath('data.wing_name', 'Senior School');

        $classId = $created->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/classes/'.$classId, ['name' => 'O Level I', 'level_order' => 140, 'next_class_id' => $classId])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.next_class_id.0', 'A class cannot promote into itself.');

        $grade10 = SchoolClass::where('name', 'Grade 10')->first();
        $grade10->update(['next_class_id' => $classId]);

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/classes/'.$classId)->assertOk();

        $this->assertNull($grade10->refresh()->next_class_id);
        $this->assertDatabaseHas('activity_logs', ['module' => 'classes', 'action' => 'deleted', 'subject_id' => $classId]);
    }

    public function test_class_with_sections_cannot_be_deleted(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/classes/'.$grade1->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'class');

        $this->assertNotSoftDeleted($grade1);
    }

    public function test_section_names_are_unique_per_class_and_session_and_reusable_after_delete(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $payload = ['academic_session_id' => $this->session->id, 'class_id' => $grade1->id, 'name' => 'A'];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/sections', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.name.0', 'This class already has a section with this name in the session.');

        $sectionA = Section::where($payload)->first();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/sections/'.$sectionA->id)->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/sections', [...$payload, 'capacity' => 25])
            ->assertCreated()
            ->assertJsonPath('data.class_name', 'Grade 1')
            ->assertJsonPath('data.capacity', 25);
    }

    public function test_sections_list_filters_by_session_and_class_in_grade_order(): void
    {
        $grade2 = SchoolClass::where('name', 'Grade 2')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/sections?class_id='.$grade2->id.'&academic_session_id='.$this->session->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.name', 'A')
            ->assertJsonPath('data.items.0.class_name', 'Grade 2');

        $rows = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/sections/datatable?'.http_build_query([
            'draw' => 1,
            'columns' => [
                ['data' => 'class_name', 'name' => 'class_name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'name', 'name' => 'sections.name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'search' => ['value' => 'Grade 2', 'regex' => 'false'],
            'length' => 10,
        ]))->assertOk()->json('data.data');

        $this->assertSame(['Grade 2', 'Grade 2'], array_column($rows, 'class_name'));
    }

    public function test_subject_codes_and_delete_protection(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/subjects', ['name' => 'Music', 'code' => 'eng'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'code');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/subjects', ['name' => 'Music', 'code' => 'mus'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MUS')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.has_practical', false);

        $english = Subject::where('code', 'ENG')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/subjects/'.$english->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'subject');
    }

    public function test_curriculum_rejects_duplicates_inactive_subjects_and_impossible_pass_marks(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $english = Subject::where('code', 'ENG')->first();
        $inactive = Subject::factory()->inactive()->create();
        $base = ['academic_session_id' => $this->session->id, 'class_id' => $grade1->id];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum', [...$base, 'subject_id' => $english->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.subject_id.0', 'This subject is already in the class curriculum for this session.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum', [...$base, 'subject_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.subject_id.0', 'Choose an active subject.');

        $music = Subject::factory()->create(['name' => 'Music']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum', [...$base, 'subject_id' => $music->id, 'theory_max_marks' => 50, 'pass_marks' => 60])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.pass_marks.0', 'Pass marks cannot be more than the total marks (50).');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum', [...$base, 'subject_id' => $music->id, 'theory_max_marks' => 40, 'practical_max_marks' => 10, 'pass_marks' => 17])
            ->assertCreated()
            ->assertJsonPath('data.subject_name', 'Music')
            ->assertJsonPath('data.total_marks', 50);
    }

    public function test_curriculum_copies_into_a_new_session_without_duplicates(): void
    {
        $next = AcademicSession::factory()->create(['name' => '2027-28', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31']);
        $sourceCount = ClassSubject::where('academic_session_id', $this->session->id)->count();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum/copy', ['from_session_id' => $this->session->id, 'to_session_id' => $next->id])
            ->assertOk()
            ->assertJsonPath('data.copied', $sourceCount);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum/copy', ['from_session_id' => $this->session->id, 'to_session_id' => $next->id])
            ->assertOk()
            ->assertJsonPath('data.copied', 0)
            ->assertJsonPath('message', 'Nothing new to copy.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/curriculum/copy', ['from_session_id' => $next->id, 'to_session_id' => $next->id])
            ->assertUnprocessable();

        $this->assertSame($sourceCount, ClassSubject::where('academic_session_id', $next->id)->count());
    }

    public function test_periods_cannot_overlap_and_must_end_after_they_start(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/periods', ['name' => 'Extra', 'starts_at' => '08:20', 'ends_at' => '08:50'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.starts_at.0', 'This time overlaps Period 1.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/periods', ['name' => 'Backwards', 'starts_at' => '15:00', 'ends_at' => '14:00'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'ends_at');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/periods', ['name' => 'Clubs', 'starts_at' => '13:40', 'ends_at' => '14:20', 'sort_order' => 110])
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '13:40')
            ->assertJsonPath('data.is_break', false);

        $period1 = Period::where('name', 'Period 1')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/periods/'.$period1->id, ['name' => 'Period 1', 'starts_at' => '08:00', 'ends_at' => '08:35'])
            ->assertOk()
            ->assertJsonPath('data.ends_at', '08:35');
    }
}
