<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Services\SystemSettingsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SystemSettingsService $systemSettingsService) {}

    public function show(): JsonResponse
    {
        return $this->successResponse(
            $this->systemSettingsService->getSettingsPageData(),
            'System settings retrieved successfully.',
        );
    }

    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->systemSettingsService->updateSettings($request->validated(), $request->file('logo')),
            'System settings updated successfully.',
        );
    }

    public function countries(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->systemSettingsService->getCountries($request->string('search')->toString()),
            'Countries retrieved successfully.',
        );
    }

    public function regions(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->systemSettingsService->getRegions(
                $request->string('search')->toString(),
                $request->integer('country_id') ?: null,
            ),
            'Regions retrieved successfully.',
        );
    }

    public function currencies(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->systemSettingsService->getCurrencies(
                $request->string('search')->toString(),
                $request->integer('country_id') ?: null,
            ),
            'Currencies retrieved successfully.',
        );
    }
}
