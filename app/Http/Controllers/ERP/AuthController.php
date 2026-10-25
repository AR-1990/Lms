<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\LoginRequest;
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
     * Email a password reset link (web "Forgot password?" page and the mobile apps). Always the same answer, so
     * nobody can find out which accounts exist.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $identifier = $request->validate(['identifier' => ['required', 'string', 'max:100']])['identifier'];
        $this->authService->sendResetLink($identifier);

        return $this->successResponse(null, 'If that account has an email address, a reset link is on its way. No email on the account? Please contact the school office.');
    }

    /**
     * Set a new password from the emailed link; every phone and browser is signed out.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        $this->authService->resetPassword([...$data, 'password_confirmation' => $request->input('password_confirmation')]);

        return $this->successResponse(null, 'Your password has been changed. Please sign in with the new password.');
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
