<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:20480'], // 20MB
            'llm_provider' => ['nullable', 'string', 'in:openai,gemini,ollama'],
        ];
    }
}
