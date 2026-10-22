<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\AssignRoleRequest;
use App\Http\Requests\ERP\CreateRoleRequest;
use App\Http\Requests\ERP\SyncUserRolesRequest;
use App\Http\Requests\ERP\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private RolePermissionService $roleService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(RoleResource::collection($this->roleService->roles())->resolve(), 'Roles retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->roleService->datatable(), 'Roles retrieved.');
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        return $this->successResponse(new RoleResource($this->roleService->createRole($request->validated())), 'Role created.', 201);
    }

    public function show(Role $role): JsonResponse
    {
        return $this->successResponse(new RoleResource($role->load('permissions')->loadCount('users')), 'Role retrieved.');
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return $this->successResponse(new RoleResource($this->roleService->updateRole($role, $request->validated())), 'Role updated.');
    }

    public function destroy(Role $role): JsonResponse
    {
        return $this->deleteWithApproval($role, 'Role deleted.');
    }

    public function assignUserRole(AssignRoleRequest $request): JsonResponse
    {
        $user = $this->roleService->assignRoleToUser(User::findOrFail($request->validated('user_id')), $request->validated('role'));

        return $this->successResponse(new UserResource($user), 'Role assigned.');
    }

    public function revokeUserRole(AssignRoleRequest $request): JsonResponse
    {
        $user = $this->roleService->revokeRoleFromUser(User::findOrFail($request->validated('user_id')), $request->validated('role'));

        return $this->successResponse(new UserResource($user), 'Role removed.');
    }

    public function syncUserRoles(SyncUserRolesRequest $request): JsonResponse
    {
        $user = $this->roleService->syncUserRoles(User::findOrFail($request->validated('user_id')), $request->validated('roles'));

        return $this->successResponse(new UserResource($user), 'User roles updated.');
    }
}
