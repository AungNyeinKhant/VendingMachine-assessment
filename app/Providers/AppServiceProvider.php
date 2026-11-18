<?php

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\JwtService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JwtService::class, function (): JwtService {
            $secret = (string) config('services.jwt.secret');

            // firebase/php-jwt rejects HS256 keys under 256 bits with a vague "key too short";
            // say exactly what to fix instead.
            if (strlen($secret) < 32) {
                throw new RuntimeException('JWT_SECRET must be at least 32 bytes. Generate one with: php -r "echo bin2hex(random_bytes(32));"');
            }

            return new JwtService(
                secret: $secret,
                issuer: (string) config('app.url'),
                ttlSeconds: (int) config('services.jwt.ttl', 3600),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // JWT guard
        Auth::viaRequest('jwt', function ($request) {
            $token = $request->bearerToken();
            $id = $token ? app(JwtService::class)->userIdFrom($token) : null;

            return $id ? app(UserRepositoryInterface::class)->findById($id) : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
