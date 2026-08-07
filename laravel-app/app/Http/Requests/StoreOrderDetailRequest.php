<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name1' => ['required_without:item_name', 'string', 'max:255'],
            'item_name' => ['required_without:service_name1', 'string', 'max:255'],
            'item_code' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'line_no' => ['nullable', 'integer', 'min:1'],
            'service_code' => ['nullable', 'string', 'max:255'],
            'service_name2' => ['nullable', 'string', 'max:255'],
            'start_time' => ['nullable', 'string', 'max:255'],
            'end_time' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'minutes' => ['nullable', 'numeric'],
            'amount' => ['nullable', 'numeric'],
            'base_unit_cost' => ['nullable', 'numeric'],
            'base_cost' => ['nullable', 'numeric'],
            'gross_profit' => ['nullable', 'numeric'],
            'gross_profit_rate' => ['nullable', 'numeric'],
            'consumption_tax' => ['nullable', 'numeric'],
            'summary' => ['nullable', 'string'],
            'line_processing_type' => ['nullable', 'string', 'max:255'],
            'tax_classification' => ['nullable', 'string', 'max:255'],
            'tax_rate' => ['nullable', 'numeric'],
        ];
    }
}
