<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'service_classification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'planned_service_date' => ['sometimes', 'nullable', 'date'],
            'planned_service_time' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_base_cost' => ['sometimes', 'nullable', 'numeric'],
            'total_gross_profit' => ['sometimes', 'nullable', 'numeric'],
            'total_consumption_tax' => ['sometimes', 'nullable', 'numeric'],
            'billing_information' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
