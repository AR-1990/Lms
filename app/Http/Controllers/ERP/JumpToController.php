<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\PortalAccessService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Jump to" search in the top bar: every page the signed-in user may open, grouped by module.
 */
class JumpToController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, PortalAccessService $portalAccessService): JsonResponse
    {
        return $this->successResponse($portalAccessService->jumpTargets($request->user()), 'Pages you can open.');
    }
}
