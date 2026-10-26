<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FeeInvoice;
use App\Models\LibraryCopy;
use App\Models\LibraryIssue;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\LibraryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $parent;

    private Student $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
        $this->child = Student::findOrFail(Student::ownIdsFor($this->parent)[0]);
        $this->actingAs($this->admin, 'sanctum');
    }

    /**
     * @return array<int, string> accession numbers of the new copies
     */
    private function book(string $title, int $copies = 2): array
    {
        $id = $this->postJson('/api/erp/library/books', ['title' => $title, 'author' => 'A. Writer', 'copies' => $copies])->assertCreated()->json('data.id');

        return LibraryCopy::where('library_book_id', $id)->orderBy('id')->pluck('accession_no')->all();
    }

    public function test_books_are_lent_returned_late_with_a_fine_challan_and_families_see_them(): void
    {
        $this->putJson('/api/erp/library/settings', ['library_loan_days' => 7, 'library_max_books' => 1, 'library_fine_per_day' => 1000])->assertOk();
        [$first, $second] = $this->book('Treasure Island');
        $this->assertSame(['ACC-000001', 'ACC-000002'], [$first, $second]);

        $this->postJson('/api/erp/library/issues', ['accession_no' => strtolower($first), 'borrower_type' => 'student', 'borrower_id' => $this->child->id])
            ->assertCreated()->assertJsonPath('data.due_on', '2026-10-08');
        $this->assertSame('issued', LibraryCopy::where('accession_no', $first)->value('status'));
        $this->postJson('/api/erp/library/issues', ['accession_no' => $first, 'borrower_type' => 'student', 'borrower_id' => $this->child->id])->assertUnprocessable()->assertJsonPath('errors.fields.accession_no.0', 'This copy is issued.');
        $this->postJson('/api/erp/library/issues', ['accession_no' => $second, 'borrower_type' => 'student', 'borrower_id' => $this->child->id])->assertUnprocessable()->assertJsonPath('errors.fields.borrower_id.0', fn (string $message): bool => str_contains($message, 'the most allowed'));
        $this->deleteJson('/api/erp/library/books/'.LibraryCopy::where('accession_no', $first)->value('library_book_id'))->assertUnprocessable();

        // Families see the book; a leaving student is not clear while it is out.
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/family/students/{$this->child->id}")->assertOk()->assertJsonPath('data.library.0.title', 'Treasure Island');
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/erp/students/{$this->child->id}/clearance")->assertOk()->assertJsonPath('data.is_clear', false)->assertJsonCount(1, 'data.library_books');

        // Overdue: reminded the day after it fell due, not again the next day.
        $this->travelTo(Carbon::parse('2026-10-09 08:30'));
        $this->assertSame(1, app(LibraryService::class)->overdueReminders());
        $this->travelTo(Carbon::parse('2026-10-10 08:30'));
        $this->assertSame(0, app(LibraryService::class)->overdueReminders());
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'library_overdue')->exists());
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/library/issues/datatable?draw=1&status=overdue')->assertOk()->assertJsonPath('data.recordsTotal', 1);

        // Returned 4 days late: Rs 40 fine on a Library Fine challan.
        $this->travelTo(Carbon::parse('2026-10-12 10:00'));
        $issue = LibraryIssue::firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/library/issues/{$issue->id}/return")->assertOk()->assertJsonPath('data.fine_amount', 4000);
        $challan = FeeInvoice::findOrFail($issue->refresh()->fee_invoice_id);
        $this->assertSame([4000, 'returned', 'available'], [$challan->total, $issue->status, $issue->copy->status]);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/library/issues/{$issue->id}/return")->assertUnprocessable();
    }

    public function test_staff_borrow_too_and_an_unreturned_book_blocks_their_exit_settlement(): void
    {
        $teacher = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->firstOrFail();
        [$copy] = $this->book('Teaching Maths', 1);

        $this->getJson('/api/erp/library/borrowers?type=employee&q='.substr($teacher->first_name, 0, 3))->assertOk()->assertJsonPath('data.0.id', $teacher->id);
        $this->postJson('/api/erp/library/issues', ['accession_no' => $copy, 'borrower_type' => 'employee', 'borrower_id' => $teacher->id])->assertCreated();
        $this->assertSame(1, app(LibraryService::class)->openFor('employee', $teacher->id)->count());

        $exit = $this->postJson('/api/erp/employee-exits', ['employee_id' => $teacher->id, 'exit_type' => 'resignation', 'last_working_day' => '2026-10-31'])->assertCreated()->json('data.id');
        $blocking = $this->getJson("/api/erp/employee-exits/{$exit}")->assertOk()->json('data.settlement.blocking');
        $this->assertTrue(collect($blocking)->contains(fn (string $line): bool => str_contains($line, '1 library book(s) not returned yet.')));

        $this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->getJson('/api/erp/library/books/datatable?draw=1')->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.library.books'))->assertOk()->assertSee('data-library-books', false);
        $this->actingAs($this->admin)->get(route('admin.library.issues'))->assertOk()->assertSee('data-issue-form', false);
    }
}
