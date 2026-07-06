<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClearChatsRequest extends FormRequest
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
            'confirmation' => ['required', 'string', 'in:DELETE ALL CHATS'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Please type "DELETE ALL CHATS" to confirm.',
            'confirmation.required' => 'Please type "DELETE ALL CHATS" to confirm.',
        ];
    }
}
