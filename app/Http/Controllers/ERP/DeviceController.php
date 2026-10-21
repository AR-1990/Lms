<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use App\Services\UserDeviceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own devices (mobile apps / browsers that hold an API token).
 */
class DeviceController extends Controller
{
    use ApiResponse;

    public function __construct(private UserDeviceService $deviceService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(
            UserDeviceResource::collection($this->deviceService->forUser($request->user()))->resolve(),
            'Devices retrieved.',
        );
    }

    /**
     * Mobile apps send their FCM / APNs token here after login or when it rotates.
     */
    public function updatePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate(['push_token' => ['required', 'string', 'max:255']]);

        $device = $this->deviceService->updatePushToken($request->user(), $validated['push_token']);

        if ($device === null) {
            return $this->errorResponse('Push tokens can only be saved from a signed-in app.', 422);
        }

        return $this->successResponse(new UserDeviceResource($device), 'Push token saved.');
    }

    public function destroy(Request $request, UserDevice $device): JsonResponse
    {
        abort_if($device->user_id !== $request->user()->id, 404);

        $this->deviceService->revoke($request->user(), $device);

        return $this->successResponse(null, 'Device signed out.');
    }
}
