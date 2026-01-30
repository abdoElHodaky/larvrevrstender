<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\UserPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates for role-based access control
        Gate::define('admin-access', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('merchant-access', function (User $user) {
            return $user->isMerchant() || $user->isAdmin();
        });

        Gate::define('customer-access', function (User $user) {
            return $user->isCustomer() || $user->isAdmin();
        });

        Gate::define('verified-user', function (User $user) {
            return $user->isVerified();
        });

        Gate::define('active-user', function (User $user) {
            return $user->isActive();
        });

        // User management gates
        Gate::define('manage-users', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('view-user', function (User $user, User $targetUser) {
            return $user->isAdmin() || $user->id === $targetUser->id;
        });

        Gate::define('update-user', function (User $user, User $targetUser) {
            return $user->isAdmin() || $user->id === $targetUser->id;
        });

        Gate::define('delete-user', function (User $user, User $targetUser) {
            return $user->isAdmin() && $user->id !== $targetUser->id;
        });

        // Profile management gates
        Gate::define('manage-profile', function (User $user, User $targetUser) {
            return $user->id === $targetUser->id || $user->isAdmin();
        });

        // Session management gates
        Gate::define('manage-sessions', function (User $user, User $targetUser) {
            return $user->id === $targetUser->id || $user->isAdmin();
        });

        Gate::define('revoke-session', function (User $user, User $targetUser) {
            return $user->id === $targetUser->id || $user->isAdmin();
        });

        // Two-factor authentication gates
        Gate::define('manage-2fa', function (User $user, User $targetUser) {
            return $user->id === $targetUser->id;
        });

        // Admin-specific gates
        Gate::define('view-admin-dashboard', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-system-settings', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('view-system-logs', function (User $user) {
            return $user->isAdmin();
        });

        // Business logic gates
        Gate::define('create-order', function (User $user) {
            return $user->isCustomer() && $user->isVerified() && $user->isActive();
        });

        Gate::define('place-bid', function (User $user) {
            return $user->isMerchant() && $user->isVerified() && $user->isActive();
        });

        Gate::define('process-payment', function (User $user) {
            return $user->isVerified() && $user->isActive();
        });

        // Analytics gates
        Gate::define('view-analytics', function (User $user) {
            return $user->isAdmin() || $user->isMerchant();
        });

        Gate::define('export-data', function (User $user) {
            return $user->isAdmin();
        });

        // Notification gates
        Gate::define('send-notification', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('manage-notification-preferences', function (User $user, User $targetUser) {
            return $user->id === $targetUser->id;
        });
    }
}

