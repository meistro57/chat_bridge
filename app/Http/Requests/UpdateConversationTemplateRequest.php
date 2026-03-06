<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationTemplateRequest extends FormRequest
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
            'max_rounds' => ['nullable', 'integer', 'min:1', 'max:500'],
            'persona_a_id' => ['required', 'exists:personas,id'],
            'persona_b_id' => ['required', 'exists:personas,id', 'different:persona_a_id'],
            'is_public' => ['required', 'boolean'],
            'rag_enabled' => ['sometimes', 'boolean'],
            'rag_source_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'rag_score_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'rag_system_prompt' => ['nullable', 'string', 'max:4000'],
            'rag_files' => ['nullable', 'array', 'max:10'],
            'rag_files.*' => ['file', 'max:10240', 'mimes:txt,md,pdf,doc,docx,csv,json'],
            'rag_files_to_delete' => ['nullable', 'array'],
            'rag_files_to_delete.*' => ['string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rag_enabled' => $this->boolean('rag_enabled', false),
        ]);
    }
}
