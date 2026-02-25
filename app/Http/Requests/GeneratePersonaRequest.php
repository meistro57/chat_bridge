<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'idea' => ['required', 'string', 'min:5', 'max:600'],
            'tone' => ['nullable', 'string', 'max:120'],
            'audience' => ['nullable', 'string', 'max:160'],
            'style' => ['nullable', 'string', 'max:160'],
            'constraints' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idea.required' => 'Describe the persona you want to generate.',
            'idea.min' => 'Provide a little more detail so the AI can build a useful persona.',
        ];
    }
}
