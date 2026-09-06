<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\AssignRoleRequest;
use App\Http\Requests\ERP\CreateRoleRequest;
use App\Http\Requests\ERP\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    use ApiResponse;

    protected RolePermissionService $roleService;

    public function __construct(RolePermissionService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * List all system and custom dynamic roles.
     */
    public function index(): JsonResponse
    {
        $roles = $this->roleService->getAllRoles();

        return $this->successResponse($roles, 'Roles retrieved successfully.');
    }

    /**
     * Create a new dynamic role.
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roleService->createRole($request->validated());

            return $this->successResponse($role, 'Role created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error.', 422, $e->errors());
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Show single role details.
     */
    public function show(string|int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRole($id);

            return $this->successResponse($role, 'Role retrieved successfully.');
        } catch (\Throwable $th) {
            return $this->errorResponse('Role not found.', 404);
        }
    }

    /**
     * Update an existing role.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $updated = $this->roleService->updateRole($role, $request->validated());

            return $this->successResponse($updated, 'Role updated successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation error.', 422, $e->errors());
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Delete a dynamic custom role.
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->roleService->deleteRole($role);

            return $this->successResponse(null, 'Role deleted successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Operation not permitted.', 422, $e->errors());
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Assign a role to a user.
     */
    public function assignUserRole(AssignRoleRequest $request): JsonResponse
    {
        try {
            $user = $this->roleService->assignRoleToUser($request->user_id, $request->role);

            return $this->successResponse([
                'user_id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name', 'slug'),
            ], 'Role assigned to user successfully.');
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 400);
        }
    }

    /**
     * Revoke a role from a user.
     */
    public function revokeUserRole(AssignRoleRequest $request): JsonResponse
    {
        try {
            $user = $this->roleService->revokeRoleFromUser($request->user_id, $request->role);

            return $this->successResponse([
                'user_id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name', 'slug'),
            ], 'Role revoked from user successfully.');
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 400);
        }
    }

    /**
     * Sync multiple roles for a user.
     */
    public function syncUserRoles(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'roles' => ['required', 'array'],
            'roles.*' => ['required'],
        ]);

        try {
            $user = $this->roleService->syncUserRoles($request->user_id, $request->roles);

            return $this->successResponse([
                'user_id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name', 'slug'),
            ], 'User roles synchronized successfully.');
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 400);
        }
    }
}
