<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
            'starter_message' => ['required', 'string', 'max:2000'],
            'max_rounds' => ['nullable', 'integer', 'min:1', 'max:100'],
            'persona_a_id' => ['required', 'exists:personas,id'],
            'persona_b_id' => ['required', 'exists:personas,id', 'different:persona_a_id'],
            'is_public' => ['required', 'boolean'],
        ];
    }
}
