<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\CreatePermissionRequest;
use App\Http\Requests\ERP\SyncRolePermissionsRequest;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    use ApiResponse;

    protected RolePermissionService $roleService;

    public function __construct(RolePermissionService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * List all permissions.
     */
    public function index(): JsonResponse
    {
        $permissions = $this->roleService->getAllPermissions();

        return $this->successResponse($permissions, 'Permissions retrieved successfully.');
    }

    /**
     * Create a new permission.
     */
    public function store(CreatePermissionRequest $request): JsonResponse
    {
        try {
            $permission = $this->roleService->createPermission($request->validated());

            return $this->successResponse($permission, 'Permission created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error.', 422, $e->errors());
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Assign permissions to a role.
     */
    public function assignRolePermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        $role->syncPermissions($validated['permissions']);

        return $this->successResponse($role->load('permissions'), 'Permissions assigned to role successfully.');
    }
}
