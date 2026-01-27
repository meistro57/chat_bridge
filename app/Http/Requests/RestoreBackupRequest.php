<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;

class RestoreBackupRequest extends FormRequest
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
            'filename' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $filename = (string) $value;
                    $sanitized = basename($filename);

                    if ($filename !== $sanitized) {
                        $fail('The selected backup is invalid.');

                        return;
                    }

                    if (! str_ends_with($sanitized, '.sql')) {
                        $fail('Backups must be .sql files.');

                        return;
                    }

                    $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$sanitized;

                    if (! File::exists($path)) {
                        $fail('The selected backup could not be found.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'filename.required' => 'Please select a backup to restore.',
        ];
    }

    private function backupDirectory(): string
    {
        return storage_path('app/backups');
    }
}
