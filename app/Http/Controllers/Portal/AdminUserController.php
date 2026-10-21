<?php

namespace App\Http\Controllers\Portal;

use App\Http\Requests\StorePortalUserRequest;
use App\Http\Requests\UpdatePortalUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends PortalController
{
    public function __construct(private UserService $userService) {}

    /**
     * Searchable, filterable user directory.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $role = $request->string('role')->trim()->value() ?: null;

        return $this->page('dashboards.admin.users', 'admin', 'users', [
            'users' => $this->userService->listUsers($role, $search)->withQueryString(),
            'roles' => $this->roleOptions(),
            'filters' => ['search' => $search, 'role' => $role],
            'counts' => [
                'total' => User::count(),
                'active_roles' => Role::count(),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->page('dashboards.admin.users.create', 'admin', 'users', [
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StorePortalUserRequest $request): RedirectResponse
    {
        $user = $this->userService->createUser($request->validated(), $request->validated('roles'));

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', "{$user->name} has been added.");
    }

    public function show(User $user): View
    {
        return $this->page('dashboards.admin.users.show', 'admin', 'users', [
            'account' => $user->load('roles.permissions'),
        ]);
    }

    public function edit(User $user): View
    {
        return $this->page('dashboards.admin.users.edit', 'admin', 'users', [
            'account' => $user->load('roles'),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UpdatePortalUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', "{$user->name} has been updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()
                ->route('admin.users')
                ->with('error', 'You cannot delete your own account.');
        }

        $this->userService->deleteUser($user);

        return redirect()
            ->route('admin.users')
            ->with('status', "{$user->name} has been deleted.");
    }

    /**
     * @return Collection<int, Role>
     */
    private function roleOptions(): Collection
    {
        return Role::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']);
    }
}
