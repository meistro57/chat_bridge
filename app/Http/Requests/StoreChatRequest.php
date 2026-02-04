<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'persona_a_id' => ['required', 'uuid', 'exists:personas,id'],
            'persona_b_id' => ['required', 'uuid', 'exists:personas,id'],
            'provider_a' => ['required', 'string'],
            'provider_b' => ['required', 'string'],
            'model_a' => ['required', 'string'],
            'model_b' => ['required', 'string'],
            'temp_a' => ['required', 'numeric', 'min:0', 'max:2'],
            'temp_b' => ['required', 'numeric', 'min:0', 'max:2'],
            'starter_message' => ['required', 'string'],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:100'],
            'stop_word_detection' => ['boolean'],
            'stop_words' => ['required_if:stop_word_detection,true', 'array'],
            'stop_words.*' => ['string'],
            'stop_word_threshold' => ['required_if:stop_word_detection,true', 'numeric', 'min:0.1', 'max:1'],
            'notifications_enabled' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notifications_enabled' => $this->boolean('notifications_enabled', true),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'persona_a_id.required' => 'Select a persona for Agent A.',
            'persona_b_id.required' => 'Select a persona for Agent B.',
            'provider_a.required' => 'Select a provider for Agent A.',
            'provider_b.required' => 'Select a provider for Agent B.',
            'model_a.required' => 'Select a model for Agent A.',
            'model_b.required' => 'Select a model for Agent B.',
            'starter_message.required' => 'Provide an initial prompt to start the session.',
            'max_rounds.required' => 'Set a maximum number of rounds.',
            'stop_words.required_if' => 'Provide at least one stop word when detection is enabled.',
            'stop_word_threshold.required_if' => 'Provide a stop word threshold when detection is enabled.',
        ];
    }
}
