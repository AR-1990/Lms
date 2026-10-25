<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Services\AttendanceKioskService;
use App\Services\AttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Attendance kiosks: managed by staff (list, add, rename / switch off, new link) and used without sign-in
 * through their secret link (page details and card scans).
 */
class AttendanceKioskController extends Controller
{
    use ApiResponse;

    public function __construct(private AttendanceKioskService $kioskService) {}

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->kioskService->datatable(), 'Kiosks retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->kioskService->create($this->validated($request, true));

        return $this->successResponse(['kiosk' => $result['kiosk'], 'url' => $result['url']], 'Kiosk added. Open the link on the gate device.', 201);
    }

    public function update(Request $request, AttendanceKiosk $attendanceKiosk): JsonResponse
    {
        return $this->successResponse($this->kioskService->update($attendanceKiosk, $this->validated($request, false)), 'Kiosk saved.');
    }

    public function renew(AttendanceKiosk $attendanceKiosk): JsonResponse
    {
        $result = $this->kioskService->renew($attendanceKiosk);

        return $this->successResponse(['kiosk' => $result['kiosk'], 'url' => $result['url']], 'New link made; the old one no longer works.');
    }

    /**
     * Public: the kiosk's name and school (the link's token is the permission).
     */
    public function show(string $token): JsonResponse
    {
        $kiosk = AttendanceKiosk::findByToken($token);
        abort_if($kiosk === null, 404, 'This kiosk link is not active.');

        return $this->successResponse($this->kioskService->page($kiosk), 'Kiosk ready.');
    }

    /**
     * Public: one card scan.
     */
    public function scan(Request $request, string $token, AttendanceService $attendanceService): JsonResponse
    {
        $kiosk = AttendanceKiosk::findByToken($token);
        abort_if($kiosk === null, 404, 'This kiosk link is not active.');
        $code = $request->validate(['code' => ['required', 'string', 'max:255']])['code'];
        $result = $this->kioskService->scan($kiosk, $code);

        return $this->successResponse($result, $attendanceService->scanMessage($result));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'campus_id' => ['nullable', 'integer', Rule::exists('campuses', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
