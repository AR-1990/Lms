<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Services\AnnouncementService;
use App\Services\ParentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    use ApiResponse;

    public function __construct(private ParentService $parentService) {}

    /**
     * Parent dashboard overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->parentService->getParentDashboard($request->user());

        return $this->successResponse($data, 'Parent dashboard retrieved successfully.');
    }

    /**
     * Get linked children profiles.
     */
    public function children(Request $request): JsonResponse
    {
        $children = $this->parentService->getChildrenProfiles($request->user());

        return $this->successResponse($children, 'Children profiles retrieved successfully.');
    }

    /**
     * Get fee ledger for linked children.
     */
    public function fees(Request $request): JsonResponse
    {
        $fees = $this->parentService->getFeeLedger($request->user(), max(1, $request->integer('page', 1)));

        return $this->successResponse($fees, 'Fee ledger retrieved successfully.');
    }

    /**
     * Announcements for the parent and their children (same feed as GET /api/erp/my-announcements).
     */
    public function notices(Request $request, AnnouncementService $announcementService): JsonResponse
    {
        $notices = AnnouncementResource::collection($announcementService->feedFor($request->user())->limit(50)->get())->resolve();

        return $this->successResponse(['notices' => $notices], 'Parent notices retrieved successfully.');
    }
}
