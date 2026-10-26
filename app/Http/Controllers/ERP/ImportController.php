<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\ImportService;
use App\Support\ApiResponseHelper;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import of students, staff and opening fee balances: check the file first, then import every row at once.
 */
class ImportController extends Controller
{
    use ApiResponse;

    public function __construct(private ImportService $importService) {}

    /**
     * The import types this user may run, with their template columns.
     */
    public function index(Request $request): JsonResponse
    {
        $types = collect(ImportService::TYPES)
            ->filter(fn (array $type): bool => empty($type['builder']) && $request->user()->hasPermission($type['permission']))
            ->map(fn (array $type, string $key): array => ['type' => $key, 'label' => $type['label'], 'columns' => $type['columns'], 'template_url' => route('erp.imports.template', $key)])
            ->values();

        return $this->successResponse(['types' => $types, 'max_rows' => ImportService::MAX_ROWS], 'Import types retrieved.');
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $this->authorizeType($request, $type);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'save' => ['sometimes', 'boolean'],
        ]);

        $result = $this->importService->run($type, $request->file('file'), (bool) ($validated['save'] ?? false), $request->user());

        if ($result['errors'] !== []) {
            $message = count($result['errors']).' problem(s) found. Nothing was imported; fix the rows and check again.';

            return ApiResponseHelper::error($message, 422, ['file' => [$message]], $result);
        }

        if (isset($result['questions'])) {
            return $this->successResponse($result, count($result['questions']).' question(s) added. Check them, then save the test.');
        }

        return $this->successResponse($result, $result['imported'] > 0 ? $result['imported'].' row(s) imported.' : $result['rows'].' row(s) are ready to import.');
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        $this->authorizeType($request, $type);

        return $this->importService->template($type);
    }

    private function authorizeType(Request $request, string $type): void
    {
        abort_unless(array_key_exists($type, ImportService::TYPES), 404);
        abort_unless($request->user()->hasPermission(ImportService::TYPES[$type]['permission']), 403);
    }
}
