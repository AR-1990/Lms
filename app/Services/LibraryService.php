<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FeeHead;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryIssue;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Library: catalogue and copies, lending to students and staff, returns with late fines (a "Library Fine"
 * challan for students), overdue reminders, and what a leaving student or staff member still has out.
 */
class LibraryService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private FeeInvoiceService $invoiceService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger,
    ) {}

    /* ---------- Catalogue ---------- */

    /**
     * @return array<string, mixed>
     */
    public function booksDatatable(): array
    {
        $query = LibraryBook::query()
            ->with('category:id,name')
            ->withCount(['copies', 'copies as available_count' => fn (Builder $copies) => $copies->where('status', 'available')]);

        return DataTables::eloquent($query)
            ->addColumn('category_name', fn (LibraryBook $book): ?string => $book->category?->name)
            ->removeColumn('category')
            ->toArray();
    }

    /**
     * A new title with its first copies (each gets the next accession number), or changes to a title.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveBook(?LibraryBook $book, array $data): LibraryBook
    {
        return DB::transaction(function () use ($book, $data): LibraryBook {
            $copies = (int) ($data['copies'] ?? 0);
            unset($data['copies']);
            $book ??= new LibraryBook(['is_active' => true]);
            $book->fill($data)->save();

            if ($copies > 0) {
                $this->addCopies($book, $copies);
            }

            return $book->loadCount('copies');
        });
    }

    public function addCopies(LibraryBook $book, int $count): void
    {
        DB::transaction(function () use ($book, $count): void {
            for ($i = 0; $i < $count; $i++) {
                $book->copies()->create(['accession_no' => $this->numberSequenceService->next('library_accession'), 'status' => 'available']);
            }
        });
    }

    /**
     * The copies of a title and who has each one now.
     *
     * @return array<int, array<string, mixed>>
     */
    public function copies(LibraryBook $book): array
    {
        $open = LibraryIssue::whereIn('library_copy_id', $book->copies()->select('id'))->where('status', 'issued')->with(['student:id,first_name,middle_name,last_name', 'employee:id,first_name,middle_name,last_name'])->get()->keyBy('library_copy_id');

        return $book->copies()->orderBy('accession_no')->get()->map(fn (LibraryCopy $copy): array => [
            'id' => $copy->id,
            'accession_no' => $copy->accession_no,
            'status' => $copy->status,
            'remarks' => $copy->remarks,
            'borrower' => $open->get($copy->id)?->borrower()?->fullName(),
            'due_on' => $open->get($copy->id)?->due_on?->toDateString(),
        ])->all();
    }

    public function changeCopyStatus(LibraryCopy $copy, string $status, ?string $remarks): LibraryCopy
    {
        if ($copy->status === 'issued') {
            throw ValidationException::withMessages(['status' => ['This copy is out on loan; return it first.']]);
        }

        DB::transaction(fn () => $copy->update(['status' => $status, 'remarks' => $remarks]));

        return $copy;
    }

    public function deleteBook(LibraryBook $book): void
    {
        if ($book->copies()->where('status', 'issued')->exists()) {
            throw ValidationException::withMessages(['book' => ['Some copies of this book are out on loan.']]);
        }

        DB::transaction(function () use ($book): void {
            $book->copies()->get()->each->delete();
            $book->delete();
        });
    }

    /* ---------- Rules ---------- */

    /**
     * @return array{library_loan_days: int, library_max_books: int, library_fine_per_day: int}
     */
    public function settings(): array
    {
        $settings = SystemSetting::query()->first(['library_loan_days', 'library_max_books', 'library_fine_per_day']);

        return ['library_loan_days' => (int) ($settings?->library_loan_days ?? 14), 'library_max_books' => (int) ($settings?->library_max_books ?? 2), 'library_fine_per_day' => (int) ($settings?->library_fine_per_day ?? 0)];
    }

    /**
     * @param  array{library_loan_days: int, library_max_books: int, library_fine_per_day: int}  $data
     * @return array{library_loan_days: int, library_max_books: int, library_fine_per_day: int}
     */
    public function saveSettings(array $data, User $user): array
    {
        SystemSetting::query()->firstOrFail()->update($data);
        $this->activityLogger->record('library', 'settings', "{$user->name} set library loans to {$data['library_loan_days']} days, {$data['library_max_books']} book(s) at a time, fine Rs ".number_format($data['library_fine_per_day'] / 100).' a day');

        return $this->settings();
    }

    /* ---------- Lending ---------- */

    /**
     * Lend a copy (by accession number) to a student or staff member, within the loan limit and with no
     * overdue book outstanding.
     *
     * @param  array{accession_no: string, borrower_type: string, borrower_id: int, due_on?: string|null, remarks?: string|null}  $data
     */
    public function issue(array $data): LibraryIssue
    {
        $copy = LibraryCopy::where('accession_no', strtoupper(trim($data['accession_no'])))->with('book:id,title')->first();

        if ($copy === null || $copy->status !== 'available') {
            throw ValidationException::withMessages(['accession_no' => [$copy === null ? 'No book has this accession number.' : 'This copy is '.$copy->status.'.']]);
        }

        $borrower = $data['borrower_type'] === 'student'
            ? Student::where('current_status', 'active')->find($data['borrower_id'])
            : Employee::whereIn('status', Employee::CURRENT_STATUSES)->find($data['borrower_id']);

        if ($borrower === null) {
            throw ValidationException::withMessages(['borrower_id' => ['Choose a current student or staff member.']]);
        }

        $rules = $this->settings();
        $open = $this->openFor($data['borrower_type'], $borrower->id)->get(['id', 'due_on']);

        if ($open->contains(fn (LibraryIssue $issue): bool => $issue->due_on->isPast() && ! $issue->due_on->isToday())) {
            throw ValidationException::withMessages(['borrower_id' => [$borrower->fullName().' has an overdue book; return it first.']]);
        }

        if ($open->count() >= $rules['library_max_books']) {
            throw ValidationException::withMessages(['borrower_id' => [$borrower->fullName().' already has '.$open->count().' book(s), the most allowed.']]);
        }

        return DB::transaction(function () use ($copy, $data, $borrower, $rules): LibraryIssue {
            $issue = LibraryIssue::create([
                'library_copy_id' => $copy->id, 'borrower_type' => $data['borrower_type'], 'borrower_id' => $borrower->id,
                'issued_on' => today()->toDateString(), 'due_on' => $data['due_on'] ?? today()->addDays($rules['library_loan_days'])->toDateString(),
                'status' => 'issued', 'remarks' => $data['remarks'] ?? null,
            ]);
            $copy->update(['status' => 'issued']);

            return $issue;
        });
    }

    /**
     * Take a copy back (or record it lost). A late return is fined per day; a student's fine becomes a
     * "Library Fine" challan.
     */
    public function returnBook(LibraryIssue $issue, bool $lost = false, ?string $remarks = null): LibraryIssue
    {
        if ($issue->status !== 'issued') {
            throw ValidationException::withMessages(['issue' => ['This book has already been '.$issue->status.'.']]);
        }

        $daysLate = max(0, (int) $issue->due_on->diffInDays(today(), false));
        $fine = $daysLate * $this->settings()['library_fine_per_day'];

        return DB::transaction(function () use ($issue, $lost, $remarks, $daysLate, $fine): LibraryIssue {
            $invoiceId = null;

            if ($fine > 0 && $issue->borrower_type === 'student' && ($head = FeeHead::where('code', 'LIB')->where('is_active', true)->first()) !== null) {
                $invoiceId = $this->invoiceService->manual([
                    'student_id' => $issue->borrower_id, 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString(),
                    'items' => [['fee_head_id' => $head->id, 'amount' => $fine, 'description' => 'Library fine: '.$daysLate.' day(s) late — '.$issue->copy?->book?->title]],
                ])->id;
            }

            $issue->update(['returned_on' => today()->toDateString(), 'status' => $lost ? 'lost' : 'returned', 'fine_amount' => $fine, 'fee_invoice_id' => $invoiceId, 'remarks' => $remarks ?? $issue->remarks]);
            $issue->copy?->update(['status' => $lost ? 'lost' : 'available']);

            return $issue;
        });
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function issuesDatatable(array $filters): array
    {
        $query = LibraryIssue::query()
            ->join('library_copies', 'library_copies.id', '=', 'library_issues.library_copy_id')
            ->join('library_books', 'library_books.id', '=', 'library_copies.library_book_id')
            ->leftJoin('students', fn ($join) => $join->on('students.id', '=', 'library_issues.borrower_id')->where('library_issues.borrower_type', 'student'))
            ->leftJoin('employees', fn ($join) => $join->on('employees.id', '=', 'library_issues.borrower_id')->where('library_issues.borrower_type', 'employee'))
            ->select('library_issues.*', 'library_copies.accession_no', 'library_books.title', 'students.first_name as student_first_name', 'students.last_name as student_last_name', 'students.admission_no', 'employees.first_name as employee_first_name', 'employees.last_name as employee_last_name', 'employees.employee_code')
            ->when(($filters['status'] ?? null) === 'overdue', fn (Builder $query) => $query->where('library_issues.status', 'issued')->whereDate('library_issues.due_on', '<', today()))
            ->when(in_array($filters['status'] ?? null, ['issued', 'returned', 'lost'], true), fn (Builder $query) => $query->where('library_issues.status', $filters['status']));

        return DataTables::eloquent($query)
            ->addColumn('borrower_name', fn (LibraryIssue $issue): string => trim($issue->borrower_type === 'student' ? $issue->student_first_name.' '.$issue->student_last_name : $issue->employee_first_name.' '.$issue->employee_last_name))
            ->addColumn('borrower_code', fn (LibraryIssue $issue): ?string => $issue->borrower_type === 'student' ? $issue->admission_no : $issue->employee_code)
            ->filterColumn('borrower_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner->where('students.first_name', 'like', "%{$keyword}%")->orWhere('students.last_name', 'like', "%{$keyword}%")->orWhere('employees.first_name', 'like', "%{$keyword}%")->orWhere('employees.last_name', 'like', "%{$keyword}%")))
            ->filterColumn('title', fn (Builder $query, string $keyword) => $query->where('library_books.title', 'like', "%{$keyword}%"))
            ->filterColumn('accession_no', fn (Builder $query, string $keyword) => $query->where('library_copies.accession_no', 'like', "%{$keyword}%"))
            ->toArray();
    }

    /**
     * Students (by name or admission no) or staff (by name or code) to lend to.
     *
     * @return array<int, array{id: int, name: string, code: string|null}>
     */
    public function borrowers(string $type, string $term): array
    {
        $like = '%'.$term.'%';

        $people = $type === 'student'
            ? Student::where('current_status', 'active')->where(fn (Builder $query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('admission_no', 'like', $like))->limit(15)->get(['id', 'first_name', 'middle_name', 'last_name', 'admission_no'])
            : Employee::whereIn('status', Employee::CURRENT_STATUSES)->where(fn (Builder $query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('employee_code', 'like', $like))->limit(15)->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_code']);

        return $people->map(fn ($person): array => ['id' => $person->id, 'name' => $person->fullName(), 'code' => $person->admission_no ?? $person->employee_code])->all();
    }

    /**
     * Books a student / staff member has out now and returned before (family page, staff app, clearances).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forBorrower(string $type, int $id): array
    {
        return LibraryIssue::where('borrower_type', $type)->where('borrower_id', $id)->with('copy.book:id,title,author')->latest('issued_on')->latest('id')->limit(30)->get()
            ->map(fn (LibraryIssue $issue): array => [
                'title' => $issue->copy?->book?->title, 'author' => $issue->copy?->book?->author, 'accession_no' => $issue->copy?->accession_no,
                'issued_on' => $issue->issued_on?->toDateString(), 'due_on' => $issue->due_on?->toDateString(), 'returned_on' => $issue->returned_on?->toDateString(),
                'status' => $issue->status, 'is_overdue' => $issue->status === 'issued' && $issue->due_on->isPast() && ! $issue->due_on->isToday(), 'fine_amount' => $issue->fine_amount,
            ])->all();
    }

    public function openFor(string $type, int $id): Builder
    {
        return LibraryIssue::where('borrower_type', $type)->where('borrower_id', $id)->where('status', 'issued');
    }

    /**
     * Daily: borrowers (a student's family, or the staff member) are reminded the day after a book falls due
     * and every week after that. Returns how many reminders were sent.
     */
    public function overdueReminders(): int
    {
        $sent = 0;

        LibraryIssue::where('status', 'issued')->whereDate('due_on', '<', today())->with(['copy.book:id,title', 'student:id,first_name,middle_name,last_name', 'employee:id,first_name,middle_name,last_name,user_id'])
            ->chunkById(200, function ($issues) use (&$sent): void {
                foreach ($issues as $issue) {
                    if ((int) $issue->due_on->diffInDays(today()) % 7 !== 1) {
                        continue;
                    }

                    $users = $issue->borrower_type === 'student' ? $this->notificationService->familyUserIds([$issue->borrower_id])->all() : array_filter([$issue->employee?->user_id]);
                    $this->notificationService->notifyEvent('library_overdue', $users, ['borrower_name' => $issue->borrower()?->fullName(), 'book_title' => $issue->copy?->book?->title, 'due_date' => $issue->due_on->format('d M Y')], $issue);
                    $sent++;
                }
            });

        return $sent;
    }
}
