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
            'template_id' => ['nullable', 'integer', 'exists:conversation_templates,id'],
            'provider_a' => ['required', 'string'],
            'provider_b' => ['required', 'string'],
            'model_a' => ['required', 'string'],
            'model_b' => ['required', 'string'],
            'starter_message' => ['required', 'string'],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:100'],
            'memory_history_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'memory_rag_enabled' => ['nullable', 'boolean'],
            'memory_rag_source_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'memory_rag_score_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'stop_word_detection' => ['boolean'],
            'stop_words' => ['required_if:stop_word_detection,true', 'array'],
            'stop_words.*' => ['string'],
            'stop_word_threshold' => ['required_if:stop_word_detection,true', 'numeric', 'min:0.1', 'max:1'],
            'notifications_enabled' => ['boolean'],
            'discord_streaming_enabled' => ['boolean'],
            'discord_webhook_url' => ['nullable', 'string', 'url'],
            'discourse_streaming_enabled' => ['boolean'],
            'discourse_topic_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'notifications_enabled' => $this->boolean('notifications_enabled', false),
        ];

        if ($this->filled('memory_history_limit')) {
            $payload['memory_history_limit'] = max(1, (int) $this->input('memory_history_limit'));
        }

        if ($this->has('memory_rag_enabled')) {
            $payload['memory_rag_enabled'] = $this->boolean('memory_rag_enabled');
        }

        if ($this->filled('memory_rag_source_limit')) {
            $payload['memory_rag_source_limit'] = max(1, (int) $this->input('memory_rag_source_limit'));
        }

        if ($this->filled('memory_rag_score_threshold')) {
            $payload['memory_rag_score_threshold'] = (float) $this->input('memory_rag_score_threshold');
        }

        if ($this->has('discord_streaming_enabled')) {
            $payload['discord_streaming_enabled'] = $this->boolean('discord_streaming_enabled');
        }

        if ($this->has('discourse_streaming_enabled')) {
            $payload['discourse_streaming_enabled'] = $this->boolean('discourse_streaming_enabled');
        }

        $this->merge($payload);
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
            'memory_history_limit.required' => 'Set how many recent messages each agent should remember.',
            'memory_rag_source_limit.required' => 'Set how many retrieved memory snippets to include.',
            'memory_rag_score_threshold.required' => 'Set a retrieval similarity threshold.',
            'stop_words.required_if' => 'Provide at least one stop word when detection is enabled.',
            'stop_word_threshold.required_if' => 'Provide a stop word threshold when detection is enabled.',
        ];
    }
}
