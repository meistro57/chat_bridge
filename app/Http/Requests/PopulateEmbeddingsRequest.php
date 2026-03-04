<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PopulateEmbeddingsRequest extends FormRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('limit')) {
            $this->merge(['limit' => 100]);
        }
    }
}
