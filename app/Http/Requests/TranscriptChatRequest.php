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
            'system_prompt' => ['nullable', 'string', 'max:4000'],
            'model' => ['nullable', 'string', 'in:gpt-4o-mini,gpt-4o,gpt-4o-2024-11-20'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_tokens' => ['nullable', 'integer', 'min:256', 'max:4096'],
            'source_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'score_threshold' => ['nullable', 'numeric', 'min:0.05', 'max:1'],
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
