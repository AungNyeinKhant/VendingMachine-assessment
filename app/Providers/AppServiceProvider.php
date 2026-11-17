<?php

namespace App\Providers;

use App\Models\User;
use App\Services\JwtService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JwtService::class, fn () => new JwtService(
            secret: (string) config('services.jwt.secret'),
            issuer: (string) config('app.url'),
            ttlSeconds: (int) config('services.jwt.ttl', 3600),
        ));
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

            return $id ? User::find($id) : null;
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
