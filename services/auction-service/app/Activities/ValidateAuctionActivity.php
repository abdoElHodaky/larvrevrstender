<?php

namespace App\Activities;

use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Validate Auction Activity
 * 
 * Validates auction parameters before creation including:
 * - Required fields validation
 * - Price validation (starting price, reserve price)
 * - Date validation (start/end times)
 * - Business rule validation
 */
class ValidateAuctionActivity implements ActivityInterface
{
    /**
     * Execute the auction validation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction validation', ['input' => $input]);

        try {
            // Define validation rules
            $rules = [
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:2000',
                'vehicle_id' => 'required|integer|exists:vehicles,id',
                'starting_price' => 'required|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'starts_at' => 'required|date|after:now',
                'ends_at' => 'required|date|after:starts_at',
                'created_by' => 'required|integer|exists:users,id',
            ];

            // Validate input data
            $validator = Validator::make($input, $rules);

            if ($validator->fails()) {
                Log::error('Auction validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $input
                ]);

                return [
                    'success' => false,
                    'errors' => $validator->errors()->toArray(),
                    'message' => 'Auction validation failed'
                ];
            }

            // Additional business rule validations
            $businessValidation = $this->validateBusinessRules($input);
            if (!$businessValidation['success']) {
                return $businessValidation;
            }

            Log::info('Auction validation completed successfully', ['input' => $input]);

            return [
                'success' => true,
                'validated_data' => $input,
                'message' => 'Auction validation completed successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Auction validation activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Auction validation activity encountered an error'
            ];
        }
    }

    /**
     * Validate business rules for auction creation
     */
    private function validateBusinessRules(array $input): array
    {
        $errors = [];

        // Rule 1: Reserve price must be >= starting price if set
        if (isset($input['reserve_price']) && $input['reserve_price'] > 0) {
            if ($input['reserve_price'] < $input['starting_price']) {
                $errors['reserve_price'] = ['Reserve price must be greater than or equal to starting price'];
            }
        }

        // Rule 2: Auction duration must be reasonable (at least 1 hour, max 30 days)
        $startTime = new \DateTime($input['starts_at']);
        $endTime = new \DateTime($input['ends_at']);
        $duration = $endTime->diff($startTime);
        
        $durationInHours = ($duration->days * 24) + $duration->h + ($duration->i / 60);
        
        if ($durationInHours < 1) {
            $errors['ends_at'] = ['Auction must run for at least 1 hour'];
        }
        
        if ($duration->days > 30) {
            $errors['ends_at'] = ['Auction cannot run for more than 30 days'];
        }

        // Rule 3: Starting price must be reasonable (not too high)
        if ($input['starting_price'] > 10000000) { // 10 million limit
            $errors['starting_price'] = ['Starting price exceeds maximum allowed amount'];
        }

        if (!empty($errors)) {
            Log::warning('Business rule validation failed', [
                'errors' => $errors,
                'input' => $input
            ]);

            return [
                'success' => false,
                'errors' => $errors,
                'message' => 'Business rule validation failed'
            ];
        }

        return ['success' => true];
    }
}

