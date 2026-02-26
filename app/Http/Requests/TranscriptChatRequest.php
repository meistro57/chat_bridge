<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TranscriptChatRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:3', 'max:2000'],
            'conversation_id' => ['nullable', 'uuid', 'exists:conversations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question.',
            'question.min' => 'Your question must be at least 3 characters.',
            'question.max' => 'Your question must not exceed 2000 characters.',
            'conversation_id.uuid' => 'Invalid conversation reference.',
            'conversation_id.exists' => 'The selected conversation does not exist.',
        ];
    }
}
