<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerProfileRequest extends FormRequest
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
            'national_id' => 'nullable|string|min:10|max:20|unique:customer_profiles,national_id',
            'national_address' => 'nullable|string|max:1000',
            'default_location' => 'nullable|array',
            'default_location.latitude' => 'required_with:default_location|numeric|between:-90,90',
            'default_location.longitude' => 'required_with:default_location|numeric|between:-180,180',
            'default_location.address' => 'nullable|string|max:500',
            'default_location.city' => 'nullable|string|max:100',
            'default_location.region' => 'nullable|string|max:100',
            'preferences' => 'nullable|array',
            'preferences.notifications' => 'nullable|boolean',
            'preferences.email_updates' => 'nullable|boolean',
            'preferences.sms_updates' => 'nullable|boolean',
            'preferences.language' => 'nullable|string|in:ar,en',
            'preferences.currency' => 'nullable|string|in:SAR,USD',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'national_id.unique' => 'This national ID is already registered.',
            'national_id.min' => 'National ID must be at least 10 characters.',
            'default_location.latitude.required_with' => 'Latitude is required when location is provided.',
            'default_location.longitude.required_with' => 'Longitude is required when location is provided.',
            'default_location.latitude.between' => 'Latitude must be between -90 and 90.',
            'default_location.longitude.between' => 'Longitude must be between -180 and 180.',
            'preferences.language.in' => 'Language must be either Arabic (ar) or English (en).',
            'preferences.currency.in' => 'Currency must be either SAR or USD.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'national_id' => 'National ID',
            'national_address' => 'National Address',
            'default_location.latitude' => 'Latitude',
            'default_location.longitude' => 'Longitude',
            'default_location.address' => 'Address',
            'default_location.city' => 'City',
            'default_location.region' => 'Region',
            'preferences.notifications' => 'Notifications',
            'preferences.email_updates' => 'Email Updates',
            'preferences.sms_updates' => 'SMS Updates',
            'preferences.language' => 'Language',
            'preferences.currency' => 'Currency',
        ];
    }
}

