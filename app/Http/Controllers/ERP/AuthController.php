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
        try {
            $deviceName = $request->input('device_name', $request->header('User-Agent', 'client-app'));
            $result = $this->authService->login($request->only('email', 'password'), $deviceName);

            return $this->successResponse($result, 'Login successful.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Authentication failed.', 422, $e->errors());
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * User Registration for Web ERP and Mobile Apps.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $roleSlug = $request->input('role', 'student');
            $deviceName = $request->input('device_name', $request->header('User-Agent', 'client-app'));

            $result = $this->authService->register($request->validated(), $roleSlug, $deviceName);

            return $this->successResponse($result, 'User registered successfully.', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Registration failed.', 422, $e->errors());
        } catch (\Throwable $th) {
            return $this->errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Get Authenticated User Profile with Roles & Permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->authService->getProfile($request->user());

        return $this->successResponse($profile, 'User profile fetched successfully.');
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
