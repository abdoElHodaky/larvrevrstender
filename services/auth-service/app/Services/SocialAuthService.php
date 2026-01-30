<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialAuthService
{
    /**
     * Handle social authentication
     */
    public function handleSocialAuth(string $provider, $socialUser): array
    {
        try {
            // Find existing user by email or social provider ID
            $user = User::where('email', $socialUser->getEmail())
                       ->orWhere("{$provider}_id", $socialUser->getId())
                       ->first();

            if ($user) {
                // Update social provider ID if not set
                if (!$user->{$provider . '_id'}) {
                    $user->update([
                        $provider . '_id' => $socialUser->getId()
                    ]);
                }
            } else {
                // Create new user
                $user = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(32)),
                    $provider . '_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'provider' => $provider,
                ]);
            }

            // Generate Sanctum token
            $token = $user->createToken('social-auth-token')->plainTextToken;

            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Social authentication failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get social redirect URL
     */
    public function getRedirectUrl(string $provider): string
    {
        return Socialite::driver($provider)->redirect()->getTargetUrl();
    }

    /**
     * Get supported providers
     */
    public function getSupportedProviders(): array
    {
        return ['google', 'facebook', 'twitter', 'github'];
    }
}

