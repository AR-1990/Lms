<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\CreateUserRequest;
use App\Http\Requests\ERP\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->paginate($this->filters($request), $request->integer('per_page', 15));

        return $this->paginatedResponse($users, UserResource::class, 'Users retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->userService->datatable($this->filters($request)), 'Users retrieved.');
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated(), $request->validated('roles'));

        return $this->successResponse(new UserResource($user->load('campus')), 'User created.', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse(new UserResource($user->load(['roles', 'campus'])), 'User retrieved.');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return $this->successResponse(new UserResource($this->userService->updateUser($user, $request->validated())->load('campus')), 'User updated.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user);

        return $this->successResponse(null, 'User deleted.');
    }

    /**
     * @return array{role: string|null, is_active: string|null, search: string|null}
     */
    private function filters(Request $request): array
    {
        $search = $request->input('search');

        return [
            'role' => is_string($request->input('role')) ? ($request->input('role') ?: null) : null,
            'is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null,
            'search' => is_string($search) ? (trim($search) ?: null) : null,
        ];
    }
}
