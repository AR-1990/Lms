<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\SaveStudentRecordRequest;
use App\Models\Student;
use App\Models\StudentCertificate;
use App\Services\StudentCareService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student's medical, discipline and achievement records, and their bonafide / character certificates.
 */
class StudentCareController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private StudentCareService $careService) {}

    /**
     * Staff see everything; the child's own family sees discipline and achievements.
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $staff = $request->user()->hasPermission('manage-students');
        abort_unless($staff || in_array($student->id, Student::ownIdsFor($request->user()), true), 404);

        return $this->successResponse($this->careService->records($student, ! $staff), 'Records retrieved.');
    }

    public function store(SaveStudentRecordRequest $request, Student $student, string $type): JsonResponse
    {
        return $this->successResponse($this->careService->save($type, null, $student, $request->validated()), 'Saved.', 201);
    }

    public function update(SaveStudentRecordRequest $request, string $type, int $id): JsonResponse
    {
        $record = $this->record($type, $id);

        return $this->successResponse($this->careService->save($type, $record, Student::findOrFail($record->student_id), $request->validated()), 'Saved.');
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        return $this->deleteWithApproval($this->record($type, $id), 'Deleted.');
    }

    public function issueCertificate(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'certificate_type' => ['required', Rule::in(array_keys(StudentCertificate::TYPES))],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'conduct_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', 'conduct')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $certificate = $this->careService->issueCertificate($student, $validated);

        return $this->successResponse([...$certificate->toArray(), 'pdf_url' => route('erp.student-certificates.pdf', $certificate)], 'Certificate '.$certificate->certificate_no.' issued.', 201);
    }

    public function certificatePdf(Request $request, StudentCertificate $studentCertificate): Response
    {
        return $this->careService->certificatePdf($studentCertificate, $request->user());
    }

    private function record(string $type, int $id): Model
    {
        abort_unless(array_key_exists($type, StudentCareService::TYPES), 404);

        return StudentCareService::TYPES[$type]::findOrFail($id);
    }
}
