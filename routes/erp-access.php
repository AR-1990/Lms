<?php

use App\Http\Controllers\ERP\PermissionController;
use App\Http\Controllers\ERP\RoleController;
use App\Http\Controllers\ERP\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:manage-roles')->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::post('roles/assign-user', [RoleController::class, 'assignUserRole'])->name('roles.assign');
    Route::post('roles/revoke-user', [RoleController::class, 'revokeUserRole'])->name('roles.revoke');
    Route::post('roles/sync-user', [RoleController::class, 'syncUserRoles'])->name('roles.sync');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::post('roles/{role}/permissions', [PermissionController::class, 'assignRolePermissions'])->name('roles.permissions');
});

Route::middleware('permission:manage-users')->apiResource('users', UserController::class);
