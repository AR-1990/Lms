<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\AssignTransportRequest;
use App\Http\Requests\Transport\EndTransportRequest;
use App\Http\Requests\Transport\SaveRouteRequest;
use App\Http\Requests\Transport\SaveVehicleRequest;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\Vehicle;
use App\Services\AssetService;
use App\Services\TransportService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Transport API (manage-transport): vehicles, routes with stops and riders; plus "my bus" for students and
 * parents. Same API for web and mobile. The route sheet PDF is in routes/export.php.
 */
class TransportController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private TransportService $transportService) {}

    public function vehiclesDatatable(): JsonResponse
    {
        return $this->successResponse($this->transportService->vehiclesDatatable(), 'Vehicles retrieved.');
    }

    public function storeVehicle(SaveVehicleRequest $request): JsonResponse
    {
        return $this->successResponse($this->transportService->saveVehicle(null, $request->validated()), 'Vehicle added.', 201);
    }

    public function updateVehicle(SaveVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        return $this->successResponse($this->transportService->saveVehicle($vehicle, $request->validated()), 'Vehicle saved.');
    }

    public function destroyVehicle(Vehicle $vehicle): JsonResponse
    {
        return $this->deleteWithApproval($vehicle, 'Vehicle deleted.');
    }

    public function routesDatatable(): JsonResponse
    {
        return $this->successResponse($this->transportService->routesDatatable(), 'Routes retrieved.');
    }

    public function showRoute(TransportRoute $transportRoute): JsonResponse
    {
        return $this->successResponse($this->transportService->route($transportRoute), 'Route retrieved.');
    }

    public function storeRoute(SaveRouteRequest $request): JsonResponse
    {
        return $this->successResponse($this->transportService->route($this->transportService->saveRoute(null, $request->validated())), 'Route added.', 201);
    }

    public function updateRoute(SaveRouteRequest $request, TransportRoute $transportRoute): JsonResponse
    {
        return $this->successResponse($this->transportService->route($this->transportService->saveRoute($transportRoute, $request->validated())), 'Route saved.');
    }

    public function destroyRoute(TransportRoute $transportRoute): JsonResponse
    {
        return $this->deleteWithApproval($transportRoute, 'Route deleted.');
    }

    /**
     * Stops of active routes for the rider picker.
     */
    public function stops(): JsonResponse
    {
        $stops = TransportStop::whereHas('route', fn ($query) => $query->where('is_active', true))->with('route:id,name,monthly_fee')->orderBy('transport_route_id')->orderBy('sort_order')->get(['id', 'transport_route_id', 'name', 'pickup_time', 'fee_override'])
            ->map(fn (TransportStop $stop): array => ['id' => $stop->id, 'name' => $stop->route?->name.' · '.$stop->name, 'fee' => $stop->fee_override ?? $stop->route?->monthly_fee])->all();

        return $this->successResponse($stops, 'Stops retrieved.');
    }

    public function students(Request $request, AssetService $assetService): JsonResponse
    {
        $term = (string) ($request->validate(['term' => ['nullable', 'string', 'max:100']])['term'] ?? '');

        return $this->successResponse($assetService->holders('student', $term), 'Students found.');
    }

    public function ridersDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['transport_route_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'ended'])]]);

        return $this->successResponse($this->transportService->ridersDatatable($filters), 'Riders retrieved.');
    }

    public function assign(AssignTransportRequest $request): JsonResponse
    {
        return $this->successResponse($this->transportService->assign($request->validated(), $request->user()), 'Student put on transport.', 201);
    }

    public function end(EndTransportRequest $request, StudentTransport $studentTransport): JsonResponse
    {
        return $this->successResponse($this->transportService->end($studentTransport, $request->validated('to_date'), $request->user()), 'Student taken off transport.');
    }

    /**
     * The bus the signed-in student rides, or each of the signed-in parent's children.
     */
    public function myBus(Request $request): JsonResponse
    {
        $user = $request->user();
        $students = Student::where('user_id', $user->id)->get()
            ->concat(Student::whereHas('guardians', fn ($query) => $query->where('user_id', $user->id))->get())
            ->unique('id');

        return $this->successResponse($students->map(fn (Student $student): array => ['student' => $student->fullName(), 'bus' => $this->transportService->busFor($student)])->values()->all(), 'Transport retrieved.');
    }

    public function routeSheet(TransportRoute $transportRoute): Response
    {
        return $this->transportService->routeSheetPdf($transportRoute);
    }
}
