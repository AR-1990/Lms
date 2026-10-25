<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\CreateManualInvoiceRequest;
use App\Http\Resources\FeeInvoiceResource;
use App\Http\Resources\StudentResource;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceBatch;
use App\Services\FeeInvoiceService;
use App\Services\StudentRecordService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FeeInvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(private FeeInvoiceService $invoiceService) {}

    /**
     * Parents and the mobile app read their challans here with ?student_id=…
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $visible = $this->invoiceService->visibleStudentIds($request->user());

        if ($visible !== null) {
            $filters['student_ids'] = $visible;
        }

        $invoices = $this->invoiceService->query($filters)->latest('issue_date')->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($invoices, FeeInvoiceResource::class, 'Challans retrieved.');
    }

    /**
     * Student picker for fee staff (who may not manage student records); shows the current-session class.
     */
    public function students(Request $request, StudentRecordService $studentService): JsonResponse
    {
        $search = $request->input('search');
        $students = $studentService->query(['search' => is_string($search) ? (trim($search) ?: null) : null])
            ->orderBy('students.first_name')
            ->paginate(15);

        return $this->paginatedResponse($students, StudentResource::class, 'Students retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->invoiceService->datatable($this->filters($request)), 'Challans retrieved.');
    }

    public function show(Request $request, FeeInvoice $feeInvoice): JsonResponse
    {
        $visible = $this->invoiceService->visibleStudentIds($request->user());
        abort_if($visible !== null && ! in_array($feeInvoice->student_id, $visible, true), 404);

        return $this->successResponse(new FeeInvoiceResource($this->invoiceService->find($feeInvoice)), 'Challan retrieved.');
    }

    /**
     * One challan as a printable PDF (staff, or the student / parent it belongs to).
     */
    public function pdf(Request $request, FeeInvoice $feeInvoice): Response
    {
        $visible = $this->invoiceService->visibleStudentIds($request->user());
        abort_if($visible !== null && ! in_array($feeInvoice->student_id, $visible, true), 404);

        return $this->invoiceService->pdf(FeeInvoice::whereKey($feeInvoice->id)->get(), "challan-{$feeInvoice->invoice_no}.pdf", $request->user());
    }

    /**
     * Every challan of a monthly batch, to print and hand out (500 at most per file).
     */
    public function batchPdf(Request $request, FeeInvoiceBatch $feeInvoiceBatch): Response
    {
        $invoices = FeeInvoice::where('fee_invoice_batch_id', $feeInvoiceBatch->id)->where('status', '!=', 'cancelled')->orderBy('invoice_no')->limit(500)->get();
        abort_if($invoices->isEmpty(), 404, 'This batch has no challans to print.');

        return $this->invoiceService->pdf($invoices, 'challans-'.$feeInvoiceBatch->billing_month->format('Y-m').'.pdf', $request->user());
    }

    public function store(CreateManualInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->manual($request->validated());

        return $this->successResponse(new FeeInvoiceResource($this->invoiceService->find($invoice)), "Challan {$invoice->invoice_no} issued.", 201);
    }

    /**
     * Monthly challans for a whole session or one class.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->whereNull('deleted_at')],
            'billing_month' => ['required', 'date_format:Y-m'],
        ]);

        $batch = $this->invoiceService->generateMonth((int) $validated['academic_session_id'], isset($validated['class_id']) ? (int) $validated['class_id'] : null, Carbon::createFromFormat('Y-m', $validated['billing_month'])->startOfMonth());

        return $this->successResponse(
            $batch->only(['id', 'generated_count', 'skipped_count', 'total_amount', 'status']),
            "{$batch->generated_count} challans generated".($batch->skipped_count > 0 ? ", {$batch->skipped_count} skipped (already billed or nothing due)." : '.'),
            201,
        );
    }

    public function cancel(Request $request, FeeInvoice $feeInvoice): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:200']], [], ['reason' => 'narration']);

        return $this->successResponse(new FeeInvoiceResource($this->invoiceService->cancel($feeInvoice, $validated['reason'])), 'Challan cancelled.');
    }

    public function waive(Request $request, FeeInvoice $feeInvoice): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [], ['reason' => 'narration']);

        return $this->successResponse(new FeeInvoiceResource($this->invoiceService->waive($feeInvoice, (int) $validated['amount'], $validated['reason'])), 'Amount waived.');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'admission_application_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['posted', 'partially_paid', 'paid', 'cancelled', 'unpaid', 'overdue'])],
            'invoice_type' => ['nullable', Rule::in(FeeInvoice::TYPES)],
            'billing_month' => ['nullable', 'date_format:Y-m'],
            'class_id' => ['nullable', 'integer'],
        ]);

        return [
            'student_id' => isset($validated['student_id']) ? (int) $validated['student_id'] : null,
            'admission_application_id' => isset($validated['admission_application_id']) ? (int) $validated['admission_application_id'] : null,
            'status' => $validated['status'] ?? null,
            'invoice_type' => $validated['invoice_type'] ?? null,
            'billing_month' => isset($validated['billing_month']) ? $validated['billing_month'].'-01' : null,
            'class_id' => isset($validated['class_id']) ? (int) $validated['class_id'] : null,
        ];
    }
}
