<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Database backups: the nightly ones, "back up now", and downloading a file to keep off the server.
 */
class BackupController extends Controller
{
    use ApiResponse;

    public function __construct(private BackupService $backupService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(['backups' => $this->backupService->list()->all(), 'keep' => BackupService::KEEP], 'Backups retrieved.');
    }

    public function store(): JsonResponse
    {
        return $this->successResponse($this->backupService->run(), 'Backup created.', 201);
    }

    public function download(string $file): StreamedResponse
    {
        return $this->backupService->download($file);
    }
}
