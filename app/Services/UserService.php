<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get paginated users with optional role filter and search term.
     */
    public function listUsers(?string $role = null, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles.permissions']);

        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('slug', $role);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get a user by ID with roles.
     */
    public function getUser(int $id): User
    {
        return User::with(['roles.permissions'])->findOrFail($id);
    }

    /**
     * Create a user with specified roles.
     */
    public function createUser(array $data, array|string $roles = 'student'): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (is_string($roles)) {
            $roles = [$roles];
        }

        $user->syncRoles($roles);

        return $user->load('roles');
    }

    /**
     * Update user details and roles.
     */
    public function updateUser(User $user, array $data): User
    {
        $updateData = [
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->load('roles');
    }

    /**
     * Delete user and their tokens.
     */
    public function deleteUser(User $user): bool
    {
        $user->tokens()->delete();
        return (bool) $user->delete();
    }
}
