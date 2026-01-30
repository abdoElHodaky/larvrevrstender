<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Check Merchant Verification Middleware
 * 
 * Ensures that merchants are verified before accessing certain routes
 */
class CheckMerchantVerification
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip check if not a merchant
        if (!$user || !$user->isMerchant()) {
            return $next($request);
        }

        // Check if merchant is verified
        if (!$user->merchant_profile || !$user->merchant_profile->verified) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant verification required',
                'error' => 'Your merchant account must be verified to access this resource.',
                'verification_status' => $user->merchant_profile?->verification_status ?? 'pending'
            ], 403);
        }

        return $next($request);
    }
}
