<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVehicleRequest extends FormRequest
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
            'brand_id' => 'required|integer|exists:brands,id',
            'model_id' => 'required|integer|exists:vehicle_models,id',
            'trim_id' => 'nullable|integer|exists:trims,id',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'vin' => 'nullable|string|size:17|regex:/^[A-HJ-NPR-Z0-9]{17}$/|unique:vehicles,vin',
            'is_primary' => 'nullable|boolean',
            'custom_name' => 'nullable|string|max:100',
            'mileage' => 'nullable|integer|min:0|max:9999999',
            'engine_type' => 'nullable|string|max:50',
            'transmission_type' => 'nullable|string|max:50',
            'fuel_type' => 'nullable|string|max:50',
            'body_style' => 'nullable|string|max:50',
            'vin_confidence' => 'nullable|numeric|min:0|max:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'brand_id.required' => 'Vehicle brand is required.',
            'brand_id.exists' => 'Selected brand does not exist.',
            'model_id.required' => 'Vehicle model is required.',
            'model_id.exists' => 'Selected model does not exist.',
            'trim_id.exists' => 'Selected trim does not exist.',
            'year.required' => 'Vehicle year is required.',
            'year.min' => 'Vehicle year must be 1900 or later.',
            'year.max' => 'Vehicle year cannot be more than one year in the future.',
            'vin.size' => 'VIN must be exactly 17 characters.',
            'vin.regex' => 'VIN format is invalid. VIN cannot contain I, O, or Q.',
            'vin.unique' => 'This VIN is already registered.',
            'mileage.min' => 'Mileage cannot be negative.',
            'mileage.max' => 'Mileage seems unrealistic.',
            'vin_confidence.min' => 'VIN confidence must be between 0 and 1.',
            'vin_confidence.max' => 'VIN confidence must be between 0 and 1.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'brand_id' => 'Brand',
            'model_id' => 'Model',
            'trim_id' => 'Trim',
            'year' => 'Year',
            'vin' => 'VIN',
            'is_primary' => 'Primary Vehicle',
            'custom_name' => 'Custom Name',
            'mileage' => 'Mileage',
            'engine_type' => 'Engine Type',
            'transmission_type' => 'Transmission Type',
            'fuel_type' => 'Fuel Type',
            'body_style' => 'Body Style',
            'vin_confidence' => 'VIN Confidence',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate brand and model relationship
            if ($this->has(['brand_id', 'model_id'])) {
                $model = \App\Models\VehicleModel::where('id', $this->model_id)
                                                ->where('brand_id', $this->brand_id)
                                                ->first();
                
                if (!$model) {
                    $validator->errors()->add('model_id', 'The selected model does not belong to the selected brand.');
                }
            }
            
            // Validate trim and model relationship
            if ($this->has(['model_id', 'trim_id']) && $this->trim_id) {
                $trim = \App\Models\Trim::where('id', $this->trim_id)
                                       ->where('model_id', $this->model_id)
                                       ->first();
                
                if (!$trim) {
                    $validator->errors()->add('trim_id', 'The selected trim does not belong to the selected model.');
                }
            }
            
            // Validate year against model availability
            if ($this->has(['model_id', 'year'])) {
                $model = \App\Models\VehicleModel::find($this->model_id);
                
                if ($model && !$model->wasAvailableInYear($this->year)) {
                    $validator->errors()->add('year', 'The selected model was not available in ' . $this->year . '.');
                }
            }
        });
    }
}

