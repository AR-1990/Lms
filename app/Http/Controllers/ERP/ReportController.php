<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports centre API: the catalogue of reports the user may open, and one report as JSON. CSV and PDF are in
 * routes/export.php. Each report checks the permission of its module.
 */
class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(private ReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse($this->reportService->catalogue($request->user()), 'Reports retrieved.');
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $filters = $this->filters($request, $report);

        return $this->successResponse($this->reportService->run($report, $filters, max(1, $request->integer('page', 1))), 'Report ready.');
    }

    public function csv(Request $request, string $report): Response
    {
        return $this->reportService->csv($report, $this->filters($request, $report), $request->user());
    }

    public function pdf(Request $request, string $report): Response
    {
        return $this->reportService->pdf($report, $this->filters($request, $report), $request->user());
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, string $report): array
    {
        abort_unless($this->reportService->allowed($report, $request->user()), 404, 'Report not found.');

        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'as_of' => ['nullable', 'date'],
            'class_id' => ['nullable', 'integer'],
            'group_by' => ['nullable', Rule::in(['day', 'method', 'class'])],
        ]);
    }
}
