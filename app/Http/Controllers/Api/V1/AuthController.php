<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group Authentication
 *
 * Get a JWT access token and inspect the current user.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Log in
     *
     * Exchange email and password for a JWT (HS256, 60 minutes). Send it as
     * `Authorization: Bearer <token>` on protected endpoints. Rate-limited to 5 attempts per minute.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Account email. Example: user@vending.test
     * @bodyParam password string required Account password. Example: password
     *
     * @response 200 {"access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...", "token_type": "Bearer", "expires_in": 3600}
     * @response 401 {"message": "Invalid credentials.", "errors": {"email": ["Invalid credentials."]}}
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->users->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
                'errors' => ['email' => ['Invalid credentials.']],
            ], 401);
        }

        return response()->json($this->jwt->issue($user));
    }

    /**
     * Current user
     *
     * @authenticated
     *
     * @response 200 {"id": 2, "name": "Demo User", "email": "user@vending.test", "permissions": ["products.purchase"]}
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
