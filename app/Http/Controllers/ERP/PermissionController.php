<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\CreatePermissionRequest;
use App\Http\Requests\ERP\SyncRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    use ApiResponse;

    public function __construct(private RolePermissionService $roleService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->roleService->permissions(), 'Permissions retrieved.');
    }

    public function store(CreatePermissionRequest $request): JsonResponse
    {
        return $this->successResponse($this->roleService->createPermission($request->validated()), 'Permission created.', 201);
    }

    public function assignRolePermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->syncPermissions($role, $request->validated('permissions'));

        return $this->successResponse(new RoleResource($role->loadCount('users')), 'Role permissions updated.');
    }
}
