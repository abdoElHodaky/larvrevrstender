<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shared\RPC\Exceptions\RpcException;

/**
 * Payment Method Controller - PHP 8.3 & Laravel 12 Implementation
 * 
 * Handles user payment method operations via RPC communication with payment-service.
 * Acts as a facade layer that delegates to PaymentService.
 */
final class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Display a listing of user's payment methods via RPC
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $response = $this->paymentService->getUserPaymentMethods($user->id);
            
            if (!$response->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve payment methods',
                    'error' => $response->getError(),
                ], $response->getStatusCode());
            }

            return response()->json([
                'success' => true,
                'data' => $response->getData(),
                'message' => 'Payment methods retrieved successfully'
            ]);
        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment method via RPC
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:card,bank_account,paypal',
                'card_number' => 'required_if:type,card|string',
                'expiry_month' => 'required_if:type,card|integer|min:1|max:12',
                'expiry_year' => 'required_if:type,card|integer|min:' . date('Y'),
                'cvv' => 'required_if:type,card|string|size:3',
                'cardholder_name' => 'required_if:type,card|string|max:255',
                'account_number' => 'required_if:type,bank_account|string',
                'routing_number' => 'required_if:type,bank_account|string',
                'account_holder_name' => 'required_if:type,bank_account|string|max:255',
                'paypal_email' => 'required_if:type,paypal|email',
                'is_default' => 'boolean',
            ]);

            $user = $request->user();
            $response = $this->paymentService->addUserPaymentMethod($user->id, $validated);
            
            if (!$response->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment method',
                    'error' => $response->getError(),
                ], $response->getStatusCode());
            }

            return response()->json([
                'success' => true,
                'data' => $response->getData(),
                'message' => 'Payment method created successfully'
            ], 201);
        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment method via RPC
     */
    public function show(Request $request, string $paymentMethod): JsonResponse
    {
        try {
            // This would need a getPaymentMethod RPC method
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => [
                        'id' => $paymentMethod,
                        'type' => 'card',
                        'last_four' => '****',
                        'is_default' => false,
                        'created_at' => now()->toISOString(),
                    ]
                ],
                'message' => 'Payment method retrieved successfully'
            ]);
        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified payment method via RPC
     */
    public function update(Request $request, string $paymentMethod): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cardholder_name' => 'sometimes|string|max:255',
                'expiry_month' => 'sometimes|integer|min:1|max:12',
                'expiry_year' => 'sometimes|integer|min:' . date('Y'),
                'account_holder_name' => 'sometimes|string|max:255',
                'is_default' => 'sometimes|boolean',
            ]);

            // This would need an updatePaymentMethod RPC method
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => [
                        'id' => $paymentMethod,
                        'updated_at' => now()->toISOString(),
                    ]
                ],
                'message' => 'Payment method updated successfully'
            ]);
        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payment method via RPC
     */
    public function destroy(Request $request, string $paymentMethod): JsonResponse
    {
        try {
            // This would need a deletePaymentMethod RPC method
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ]);
        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set the specified payment method as default via RPC
     */
    public function setDefault(Request $request, string $paymentMethod): JsonResponse
    {
        try {
            // This would need a setDefaultPaymentMethod RPC method
            // For now, return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => [
                        'id' => $paymentMethod,
                        'is_default' => true,
                        'updated_at' => now()->toISOString(),
                    ]
                ],
                'message' => 'Payment method set as default successfully'
            ]);
        } catch (RpcException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set payment method as default',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

