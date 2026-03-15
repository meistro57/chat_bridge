<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrchestratorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'goal' => ['nullable', 'string', 'max:10000'],
            'is_scheduled' => ['boolean'],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_number' => ['required', 'integer', 'min:1'],
            'steps.*.label' => ['nullable', 'string', 'max:255'],
            'steps.*.template_id' => ['nullable', 'integer'],
            'steps.*.persona_a_id' => ['nullable', 'uuid'],
            'steps.*.persona_b_id' => ['nullable', 'uuid'],
            'steps.*.provider_a' => ['nullable', 'string', 'max:100'],
            'steps.*.model_a' => ['nullable', 'string', 'max:255'],
            'steps.*.provider_b' => ['nullable', 'string', 'max:100'],
            'steps.*.model_b' => ['nullable', 'string', 'max:255'],
            'steps.*.input_source' => ['required', 'string', 'in:static,previous_step_output,variable'],
            'steps.*.input_value' => ['nullable', 'string', 'max:20000'],
            'steps.*.input_variable_name' => ['nullable', 'string', 'max:255'],
            'steps.*.output_action' => ['required', 'string', 'in:log,pass_to_next,webhook,store_as_variable'],
            'steps.*.output_variable_name' => ['nullable', 'string', 'max:255'],
            'steps.*.output_webhook_url' => ['nullable', 'url', 'max:2048'],
            'steps.*.condition' => ['nullable', 'array'],
            'steps.*.pause_before_run' => ['boolean'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.required' => 'At least one step is required.',
            'steps.min' => 'At least one step is required.',
            'steps.*.step_number.required' => 'Each step must have a step number.',
            'steps.*.input_source.in' => 'Input source must be static, previous_step_output, or variable.',
            'steps.*.output_action.in' => 'Output action must be log, pass_to_next, webhook, or store_as_variable.',
        ];
    }
}
