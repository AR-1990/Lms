<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeInvoiceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->actingAs($this->admin, 'sanctum');
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function csv(array $header, array $rows): UploadedFile
    {
        $lines = array_map(fn (array $row): string => implode(',', array_map(fn (string $value): string => str_contains($value, ',') ? '"'.$value.'"' : $value, $row)), [$header, ...$rows]);

        return UploadedFile::fake()->createWithContent('import.csv', "\u{FEFF}".implode("\n", $lines)."\n");
    }

    private function import(string $type, UploadedFile $file, bool $save): TestResponse
    {
        return $this->post("/api/erp/imports/{$type}", ['file' => $file, 'save' => $save ? '1' : '0'], ['Accept' => 'application/json']);
    }

    /**
     * @return array<int, string>
     */
    private function studentHeader(): array
    {
        return ['first_name*', 'last_name', 'gender*', 'date_of_birth*', 'class*', 'section', 'guardian_name*', 'guardian_relation', 'guardian_phone*', 'guardian_cnic'];
    }

    public function test_students_are_checked_then_imported_with_their_guardians(): void
    {
        $file = fn (): UploadedFile => $this->csv($this->studentHeader(), [
            ['Hamza', 'Iqbal', 'Male', '12/03/2018', 'Grade 3', 'A', 'Iqbal Ahmed', 'Father', '03001234567', '35202-7777771-7'],
            ['Hira', 'Iqbal', 'female', '2016-07-09', 'Grade 5', '', 'Iqbal Ahmed', '', '03001234567', '35202-7777771-7'],
        ]);
        $before = Student::count();

        $this->import('students', $file(), false)->assertOk()->assertJsonPath('data.rows', 2)->assertJsonPath('data.imported', 0);
        $this->assertSame($before, Student::count());

        $this->import('students', $file(), true)->assertOk()->assertJsonPath('data.imported', 2);

        $hamza = Student::where('first_name', 'Hamza')->firstOrFail();
        $this->assertSame('2018-03-12', $hamza->date_of_birth->toDateString());
        $this->assertSame('Grade 3', $hamza->currentEnrollment->schoolClass->name);
        $this->assertSame('A', $hamza->currentEnrollment->section->name);
        $this->assertSame(1, Guardian::where('cnic', '35202-7777771-7')->count());
        $this->assertSame(2, Guardian::where('cnic', '35202-7777771-7')->first()->students()->count());
        $this->assertDatabaseHas('activity_logs', ['module' => 'imports', 'action' => 'imported']);
    }

    public function test_any_bad_row_stops_the_whole_import_and_names_the_rows(): void
    {
        $file = $this->csv($this->studentHeader(), [
            ['Ali', 'Khan', 'Male', '2017-01-01', 'Grade 3', '', 'Khan Sahib', 'Father', '03009999999', ''],
            ['Sara', '', 'Unknown', '2017-01-01', 'Grade 3', '', 'Parent', 'Father', '03008888888', ''],
            ['Zoya', '', 'Female', 'yesterday', 'Grade 99', '', '', 'Father', '', ''],
        ]);
        $before = Student::count();

        $response = $this->import('students', $file, true)->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('data.valid', 1);
        $this->assertSame([3, 4], array_values(array_unique(array_column($response->json('data.errors'), 'row'))));
        $this->assertStringContainsString('Gender "Unknown" is not in the list', implode(' ', $response->json('data.errors.0.messages')));
        $this->assertSame($before, Student::count());
    }

    public function test_staff_rows_become_employees(): void
    {
        $file = $this->csv(['first_name*', 'last_name', 'gender*', 'cnic', 'phone*', 'department', 'designation*', 'employment_type*', 'joining_date*'], [
            ['Ayesha', 'Noor', 'Female', '35202-2222222-2', '03211234567', 'Academics', 'Teacher', 'Permanent', '01/08/2026'],
        ]);

        $this->import('staff', $file, true)->assertOk()->assertJsonPath('data.imported', 1);

        $employee = Employee::where('cnic', '35202-2222222-2')->firstOrFail();
        $this->assertSame('Teacher', $employee->designation->name);
        $this->assertSame('2026-08-01', $employee->joining_date->toDateString());
        $this->assertNotNull($employee->employee_code);
    }

    public function test_opening_balances_become_a_previous_balance_challan_or_an_advance(): void
    {
        $students = Student::whereHas('currentEnrollment')->orderBy('id')->take(2)->get();
        $file = $this->csv(['admission_no*', 'amount*', 'as_of_date*', 'note'], [
            [$students[0]->admission_no, '4,500', '2026-08-01', 'Unpaid July fee'],
            [$students[1]->admission_no, '-2000', '2026-08-01', ''],
        ]);

        $this->import('opening_balances', $file, true)->assertOk()->assertJsonPath('data.imported', 2);

        $challan = FeeInvoice::where('student_id', $students[0]->id)->whereHas('items.feeHead', fn ($query) => $query->where('code', 'OPB'))->firstOrFail();
        $this->assertSame(450000, $challan->balance);
        $this->assertSame(200000, app(FeeInvoiceService::class)->advanceBalance($students[1]->id));

        $this->import('opening_balances', $this->csv(['admission_no*', 'amount*', 'as_of_date*'], [['NO-SUCH', '100', '2026-08-01']]), false)
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 2);
    }

    public function test_templates_and_permissions(): void
    {
        $this->getJson('/api/erp/imports')->assertOk()->assertJsonCount(4, 'data.types');

        $template = $this->get('/api/erp/imports/students/template')->assertOk();
        $this->assertStringStartsWith('admission_no,first_name*', $template->streamedContent());

        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/imports')->assertOk()->assertJsonCount(0, 'data.types');
        $this->actingAs($teacher, 'sanctum')->post('/api/erp/imports/students', ['file' => $this->csv($this->studentHeader(), [])], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($teacher)->get('/admin/imports')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/imports')->assertOk()->assertSee('Data Import');
        $this->getJson('/api/erp/imports/unknown/template')->assertNotFound();
    }
}
