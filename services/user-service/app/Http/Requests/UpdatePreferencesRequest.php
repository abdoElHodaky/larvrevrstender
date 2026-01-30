<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
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
            'notifications' => 'nullable|boolean',
            'email_updates' => 'nullable|boolean',
            'sms_updates' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'language' => 'nullable|string|in:ar,en',
            'currency' => 'nullable|string|in:SAR,USD',
            'timezone' => 'nullable|string|max:50',
            'notification_frequency' => 'nullable|string|in:immediate,daily,weekly',
            'marketing_emails' => 'nullable|boolean',
            'order_updates' => 'nullable|boolean',
            'bid_notifications' => 'nullable|boolean',
            'price_alerts' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'language.in' => 'Language must be either Arabic (ar) or English (en).',
            'currency.in' => 'Currency must be either SAR or USD.',
            'notification_frequency.in' => 'Notification frequency must be immediate, daily, or weekly.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'notifications' => 'Notifications',
            'email_updates' => 'Email Updates',
            'sms_updates' => 'SMS Updates',
            'push_notifications' => 'Push Notifications',
            'language' => 'Language',
            'currency' => 'Currency',
            'timezone' => 'Timezone',
            'notification_frequency' => 'Notification Frequency',
            'marketing_emails' => 'Marketing Emails',
            'order_updates' => 'Order Updates',
            'bid_notifications' => 'Bid Notifications',
            'price_alerts' => 'Price Alerts',
        ];
    }
}

