<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryWithChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider_a' => ['nullable', 'string', 'max:50'],
            'model_a' => ['nullable', 'string', 'max:200'],
            'provider_b' => ['nullable', 'string', 'max:50'],
            'model_b' => ['nullable', 'string', 'max:200'],
        ];
    }
}
