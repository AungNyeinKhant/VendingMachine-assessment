# JWT templates (Task 17)

## JwtService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class JwtService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $ttlSeconds = 3600,
    ) {}

    /** @return array{access_token: string, token_type: string, expires_in: int} */
    public function issue(User $user): array
    {
        $now = time();

        $token = JWT::encode([
            'iss' => $this->issuer,
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
            'jti' => (string) Str::uuid(),
        ], $this->secret, self::ALGORITHM);

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $this->ttlSeconds];
    }

    /** Returns the user id, or null for expired / tampered / malformed / wrong-issuer tokens. */
    public function userIdFrom(string $token): ?int
    {
        try {
            // Key pins the algorithm: tokens signed with anything but HS256 (incl. "none") are rejected.
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable) {
            return null;
        }

        return ($claims->iss ?? null) === $this->issuer && isset($claims->sub) ? (int) $claims->sub : null;
    }
}
```

Bind it with config values (constructor takes scalars, so the container needs help):

```php
// AppServiceProvider::register()
$this->app->singleton(JwtService::class, fn () => new JwtService(
    secret: (string) config('services.jwt.secret'),
    issuer: (string) config('app.url'),
    ttlSeconds: (int) config('services.jwt.ttl', 3600),
));
```

`config/services.php`: `'jwt' => ['secret' => env('JWT_SECRET'), 'ttl' => env('JWT_TTL', 3600)]`. Fail fast at boot in production if the secret is shorter than 32 bytes.

## Guard

```php
// AppServiceProvider::boot()
Auth::viaRequest('jwt', function (Request $request) {
    $token = $request->bearerToken();
    $id = $token ? app(JwtService::class)->userIdFrom($token) : null;

    return $id ? User::find($id) : null;
});
```

(`app()` is acceptable here: provider boot code is wiring, not a domain class.)

`config/auth.php`:

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'api' => ['driver' => 'jwt', 'provider' => 'users'],
],
```

## Routes

```php
// routes/api.php  (registered with prefix "api" in bootstrap/app.php)
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::middleware('auth:api')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('products/{product}/purchase', [PurchaseController::class, 'store'])->middleware('permission:products.purchase');
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::middleware('permission:products.manage')->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
        });
    });
});
```

`auth:api` calls `shouldUse('api')`, so spatie's `permission:` middleware finds the JWT user. Together with `$guard_name = 'web'` on `User`, permission lookups use the seeded `web` permissions.

## Errors

In `bootstrap/app.php` → `withExceptions`: `$exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));` so 401/403/404/422 from the API are always JSON. Domain exceptions render their own envelope (see vending-backend-structure templates).

## Login controller (shape)

```php
public function login(Request $request): JsonResponse
{
    $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials.', 'errors' => ['email' => ['Invalid credentials.']]], 401);
    }

    return response()->json($this->jwt->issue($user));
}
```

Same message for unknown email and wrong password (no user enumeration).
Users deliberately have no repository: Fortify owns user persistence, so the API login reads the model directly. That's the one documented exception to "controllers don't query".
