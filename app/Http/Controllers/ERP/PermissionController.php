<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:permissions,slug'],
            'group' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $permission = $this->roleService->createPermission($request->all());

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
    public function assignRolePermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required'],
        ]);

        $role->syncPermissions($request->permissions);

        return $this->successResponse($role->load('permissions'), 'Permissions assigned to role successfully.');
    }
}
