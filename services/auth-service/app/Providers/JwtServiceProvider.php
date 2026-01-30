<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\JWTGuard;
use Illuminate\Support\Facades\Auth;

class JwtServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register JWT service
        $this->app->singleton('jwt.service', function ($app) {
            return new \App\Services\JwtService($app['tymon.jwt.auth']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Extend Auth guard with JWT
        Auth::extend('jwt', function ($app, $name, array $config) {
            return new JWTGuard(
                $app['tymon.jwt'],
                $app['auth']->createUserProvider($config['provider']),
                $app['request']
            );
        });

        // Configure JWT settings
        $this->configureJWT();
    }

    /**
     * Configure JWT settings
     */
    private function configureJWT(): void
    {
        // Set custom claims
        config([
            'jwt.custom_claims' => [
                'iss' => config('app.url'),
                'aud' => config('app.url'),
                'sub' => 'auth-service',
            ]
        ]);

        // Set blacklist grace period
        config([
            'jwt.blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0)
        ]);
    }
}

