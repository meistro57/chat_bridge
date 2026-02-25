<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunAnalyticsSqlRequest extends FormRequest
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
            'sql' => ['required', 'string', 'max:20000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sql.required' => 'Enter a SQL query to run.',
            'sql.max' => 'SQL query must be less than 20,000 characters.',
            'limit.integer' => 'Row limit must be a number.',
            'limit.min' => 'Row limit must be at least 1.',
            'limit.max' => 'Row limit cannot exceed 500.',
        ];
    }
}
