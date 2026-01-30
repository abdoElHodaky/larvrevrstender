<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMerchantProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'business_name' => 'required|string|max:255|unique:merchant_profiles,business_name',
            'business_license' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50|unique:merchant_profiles,tax_number',
            'specializations' => 'nullable|array',
            'specializations.*' => 'string|max:100',
            'verification_documents' => 'nullable|array',
            'verification_documents.*' => 'string|max:500',
            'business_hours' => 'nullable|array',
            'business_hours.*.day' => 'required_with:business_hours|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'business_hours.*.open' => 'required_with:business_hours|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'business_hours.*.close' => 'required_with:business_hours|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'service_areas' => 'nullable|array',
            'service_areas.*' => 'string|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'Business name is required.',
            'business_name.unique' => 'This business name is already registered.',
            'tax_number.unique' => 'This tax number is already registered.',
            'specializations.*.string' => 'Each specialization must be a valid text.',
            'business_hours.*.day.in' => 'Day must be a valid weekday.',
            'business_hours.*.open.regex' => 'Opening time must be in HH:MM format.',
            'business_hours.*.close.regex' => 'Closing time must be in HH:MM format.',
            'service_areas.*.string' => 'Each service area must be a valid text.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'business_name' => 'Business Name',
            'business_license' => 'Business License',
            'tax_number' => 'Tax Number',
            'specializations' => 'Specializations',
            'verification_documents' => 'Verification Documents',
            'business_hours' => 'Business Hours',
            'service_areas' => 'Service Areas',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate business hours logic
            if ($this->has('business_hours')) {
                foreach ($this->business_hours as $index => $hours) {
                    if (isset($hours['open'], $hours['close'])) {
                        $openTime = strtotime($hours['open']);
                        $closeTime = strtotime($hours['close']);
                        
                        if ($openTime >= $closeTime) {
                            $validator->errors()->add(
                                "business_hours.{$index}.close",
                                'Closing time must be after opening time.'
                            );
                        }
                    }
                }
            }
        });
    }
}

