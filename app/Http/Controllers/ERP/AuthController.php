<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\LoginRequest;
use App\Http\Requests\ERP\RegisterRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * User Login for Web ERP and Mobile Apps.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $deviceName = $request->input('device_name', $request->header('User-Agent', 'client-app'));

        try {
            $result = $this->authService->login(
                $request->only('email', 'password'),
                $deviceName,
                $request->only('device_id', 'platform', 'app_version', 'push_token'),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Authentication failed.', 422, $e->errors());
        }

        return $this->successResponse($result, 'Login successful.');
    }

    /**
     * User Registration for Web ERP and Mobile Apps.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $roleSlug = $request->input('role', 'student');
        $deviceName = $request->input('device_name', $request->header('User-Agent', 'client-app'));

        try {
            $result = $this->authService->register($request->validated(), $roleSlug, $deviceName);
        } catch (ValidationException $e) {
            return $this->errorResponse('Registration failed.', 422, $e->errors());
        }

        return $this->successResponse($result, 'User registered successfully.', 201);
    }

    /**
     * Get Authenticated User Profile with Roles & Permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->authService->getProfile(
            $request->user(),
            $request->user()->currentAccessToken()?->name,
        );

        return $this->successResponse($profile, 'Authenticated user profile fetched successfully.');
    }

    /**
     * Logout and invalidate access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
