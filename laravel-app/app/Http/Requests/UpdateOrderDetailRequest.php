<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name1' => ['sometimes', 'string', 'max:255'],
            'item_name' => ['sometimes', 'string', 'max:255'],
            'item_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'line_no' => ['sometimes', 'integer', 'min:1'],
            'service_code' => ['sometimes', 'string', 'max:255'],
            'service_name2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_time' => ['sometimes', 'nullable', 'string', 'max:255'],
            'end_time' => ['sometimes', 'nullable', 'string', 'max:255'],
            'duration' => ['sometimes', 'nullable', 'string', 'max:255'],
            'minutes' => ['sometimes', 'nullable', 'numeric'],
            'amount' => ['sometimes', 'nullable', 'numeric'],
            'base_unit_cost' => ['sometimes', 'nullable', 'numeric'],
            'base_cost' => ['sometimes', 'nullable', 'numeric'],
            'gross_profit' => ['sometimes', 'nullable', 'numeric'],
            'gross_profit_rate' => ['sometimes', 'nullable', 'numeric'],
            'consumption_tax' => ['sometimes', 'nullable', 'numeric'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'line_processing_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_classification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_rate' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}
