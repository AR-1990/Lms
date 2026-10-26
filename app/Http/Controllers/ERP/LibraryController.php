<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveLibraryBookRequest;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryIssue;
use App\Services\LibraryService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Library: catalogue, copies, lending and returns, loan rules.
 */
class LibraryController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private LibraryService $libraryService) {}

    public function booksDatatable(): JsonResponse
    {
        return $this->successResponse($this->libraryService->booksDatatable(), 'Books retrieved.');
    }

    public function showBook(LibraryBook $libraryBook): JsonResponse
    {
        return $this->successResponse([...$libraryBook->load('category:id,name')->toArray(), 'copies' => $this->libraryService->copies($libraryBook)], 'Book retrieved.');
    }

    public function storeBook(SaveLibraryBookRequest $request): JsonResponse
    {
        $book = $this->libraryService->saveBook(null, $request->validated());

        return $this->successResponse($book, "Book added with {$book->copies_count} cop".($book->copies_count === 1 ? 'y' : 'ies').'.', 201);
    }

    public function updateBook(SaveLibraryBookRequest $request, LibraryBook $libraryBook): JsonResponse
    {
        return $this->successResponse($this->libraryService->saveBook($libraryBook, $request->validated()), 'Book saved.');
    }

    public function destroyBook(LibraryBook $libraryBook): JsonResponse
    {
        return $this->deleteWithApproval($libraryBook, 'Book removed from the catalogue.');
    }

    public function changeCopy(Request $request, LibraryCopy $libraryCopy): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'lost', 'damaged', 'withdrawn'])], 'remarks' => ['nullable', 'string', 'max:255']]);

        return $this->successResponse($this->libraryService->changeCopyStatus($libraryCopy, $data['status'], $data['remarks'] ?? null), 'Copy updated.');
    }

    public function settings(): JsonResponse
    {
        return $this->successResponse($this->libraryService->settings(), 'Library rules.');
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'library_loan_days' => ['required', 'integer', 'min:1', 'max:90'],
            'library_max_books' => ['required', 'integer', 'min:1', 'max:20'],
            'library_fine_per_day' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        return $this->successResponse($this->libraryService->saveSettings($data, $request->user()), 'Library rules saved.');
    }

    public function borrowers(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['student', 'employee'])], 'q' => ['required', 'string', 'min:2', 'max:60']]);

        return $this->successResponse($this->libraryService->borrowers($data['type'], $data['q']), 'Matching people.');
    }

    public function issuesDatatable(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['issued', 'overdue', 'returned', 'lost'])]])['status'] ?? null;

        return $this->successResponse($this->libraryService->issuesDatatable(['status' => $status]), 'Loans retrieved.');
    }

    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accession_no' => ['required', 'string', 'max:30'],
            'borrower_type' => ['required', Rule::in(['student', 'employee'])],
            'borrower_id' => ['required', 'integer'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $issue = $this->libraryService->issue($data);

        return $this->successResponse($issue, 'Issued; due back on '.$issue->due_on->format('d M Y').'.', 201);
    }

    public function returnBook(Request $request, LibraryIssue $libraryIssue): JsonResponse
    {
        $data = $request->validate(['lost' => ['sometimes', 'boolean'], 'remarks' => ['nullable', 'string', 'max:255']]);
        $issue = $this->libraryService->returnBook($libraryIssue, (bool) ($data['lost'] ?? false), $data['remarks'] ?? null);

        return $this->successResponse($issue, ($issue->status === 'lost' ? 'Marked lost.' : 'Returned.').($issue->fine_amount > 0 ? ' Late fine Rs '.number_format($issue->fine_amount / 100).($issue->fee_invoice_id ? ' added as a challan.' : '.') : ''));
    }
}
