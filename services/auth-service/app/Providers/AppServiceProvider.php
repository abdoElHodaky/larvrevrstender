<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service bindings
        $this->app->singleton('auth.service', function ($app) {
            return new \App\Services\AuthService();
        });

        $this->app->singleton('otp.service', function ($app) {
            return new \App\Services\OtpService();
        });

        $this->app->singleton('social.service', function ($app) {
            return new \App\Services\SocialAuthService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for MySQL
        Schema::defaultStringLength(191);

        // Register custom validation rules
        $this->registerValidationRules();

        // Register custom macros
        $this->registerMacros();
    }

    /**
     * Register custom validation rules
     */
    private function registerValidationRules(): void
    {
        \Validator::extend('saudi_phone', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\+966[0-9]{9}$/', $value);
        });

        \Validator::extend('saudi_national_id', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[12][0-9]{9}$/', $value);
        });

        \Validator::extend('strong_password', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $value);
        });
    }

    /**
     * Register custom macros
     */
    private function registerMacros(): void
    {
        // Add custom response macros
        \Response::macro('success', function ($data = null, $message = 'Success', $code = 200) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ], $code);
        });

        \Response::macro('error', function ($message = 'Error', $errors = null, $code = 400) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'timestamp' => now()->toISOString()
            ], $code);
        });
    }
}

