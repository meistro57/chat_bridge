<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrchestratorRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'goal' => ['nullable', 'string', 'max:10000'],
            'is_scheduled' => ['sometimes', 'boolean'],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'steps' => ['sometimes', 'array', 'min:1'],
            'steps.*.step_number' => ['required_with:steps', 'integer', 'min:1'],
            'steps.*.label' => ['nullable', 'string', 'max:255'],
            'steps.*.template_id' => ['nullable', 'integer'],
            'steps.*.persona_a_id' => ['nullable', 'uuid'],
            'steps.*.persona_b_id' => ['nullable', 'uuid'],
            'steps.*.provider_a' => ['nullable', 'string', 'max:100'],
            'steps.*.model_a' => ['nullable', 'string', 'max:255'],
            'steps.*.provider_b' => ['nullable', 'string', 'max:100'],
            'steps.*.model_b' => ['nullable', 'string', 'max:255'],
            'steps.*.input_source' => ['sometimes', 'string', 'in:static,previous_step_output,variable'],
            'steps.*.input_value' => ['nullable', 'string', 'max:20000'],
            'steps.*.input_variable_name' => ['nullable', 'string', 'max:255'],
            'steps.*.output_action' => ['sometimes', 'string', 'in:log,pass_to_next,webhook,store_as_variable'],
            'steps.*.output_variable_name' => ['nullable', 'string', 'max:255'],
            'steps.*.output_webhook_url' => ['nullable', 'url', 'max:2048'],
            'steps.*.condition' => ['nullable', 'array'],
            'steps.*.pause_before_run' => ['boolean'],
        ];
    }
}
