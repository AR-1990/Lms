<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\CreateUserRequest;
use App\Http\Requests\ERP\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * List users with optional role filter, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 15);

        $users = $this->userService->listUsers($role, $search, $perPage);

        return $this->successResponse($users, 'Users retrieved successfully.');
    }

    /**
     * Create a new user with role.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $roles = $request->input('roles');
        $user = $this->userService->createUser($request->validated(), $roles);

        return $this->successResponse($user, 'User created successfully.', 201);
    }

    /**
     * Show single user details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->getUser($id);

            return $this->successResponse($user, 'User retrieved successfully.');
        } catch (\Throwable $th) {
            return $this->errorResponse('User not found.', 404);
        }
    }

    /**
     * Update user details and roles.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->userService->updateUser($user, $request->validated());

        return $this->successResponse($updated, 'User updated successfully.');
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user);

        return $this->successResponse(null, 'User deleted successfully.');
    }
}
