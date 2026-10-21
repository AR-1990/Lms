<?php

namespace App\Http\Controllers\Portal;

use App\Http\Requests\StorePortalUserRequest;
use App\Http\Requests\UpdatePortalUserRequest;
use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Services\LookupService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminUserController extends PortalController
{
    public function __construct(private UserService $userService) {}

    /**
     * User directory: the grid loads from /api/erp/users/datatable.
     */
    public function index(): View
    {
        return $this->page('dashboards.admin.users', 'admin', 'users', [
            'roles' => $this->roleOptions(),
            'counts' => [...$this->userService->summary(), 'roles' => Role::count()],
        ]);
    }

    public function create(): View
    {
        return $this->page('dashboards.admin.users.create', 'admin', 'users', [
            'roles' => $this->roleOptions(),
            'campuses' => $this->campusOptions(),
        ]);
    }

    public function store(StorePortalUserRequest $request): RedirectResponse
    {
        $user = $this->userService->createUser($request->validated(), $request->validated('roles'));

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', "{$user->name} has been added.");
    }

    /**
     * Profile with access summary; the documents panel uses /api/erp/attachments.
     */
    public function show(User $user, LookupService $lookupService): View
    {
        return $this->page('dashboards.admin.users.show', 'admin', 'users', [
            'account' => $user->load(['roles.permissions', 'campus']),
            'documentTypes' => $lookupService->options('document_type'),
        ]);
    }

    public function edit(User $user): View
    {
        return $this->page('dashboards.admin.users.edit', 'admin', 'users', [
            'account' => $user->load('roles'),
            'roles' => $this->roleOptions(),
            'campuses' => $this->campusOptions($user->campus_id),
        ]);
    }

    public function update(UpdatePortalUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', "{$user->name} has been updated.");
    }

    /**
     * @return Collection<int, Role>
     */
    private function roleOptions(): Collection
    {
        return Role::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']);
    }

    /**
     * Active campuses, plus the account's current one even if it was deactivated.
     *
     * @return Collection<int, Campus>
     */
    private function campusOptions(?int $currentCampusId = null): Collection
    {
        return Campus::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $currentCampusId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
