<?php

namespace App\Services\System;

use Illuminate\Support\Facades\File;

class EnvFileService
{
    /**
     * Update or add a key to the .env file.
     */
    public function updateKey(string $key, string $value): void
    {
        $path = base_path('.env');
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        
        // Escape value if it contains spaces or special characters
        if (preg_match('/\s/', $value) || str_contains($value, '#')) {
            $value = '"' . str_replace('"', '\"', $value) . '"';
        }

        // Check if key exists
        if (preg_match("/^{$key}=.*/m", $content)) {
            // Replace existing
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Append new
            $content .= "\n{$key}={$value}\n";
        }

        File::put($path, $content);
    }

    /**
     * Map provider name (slug) to Env Key.
     */
    public function getEnvKey(string $provider): string
    {
        return match (strtolower($provider)) {
            'openai' => 'OPENAI_API_KEY',
            'anthropic' => 'ANTHROPIC_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            'gemini' => 'GEMINI_API_KEY',
            'openrouter' => 'OPENROUTER_API_KEY',
            'postmark' => 'POSTMARK_API_KEY',
            'resend' => 'RESEND_API_KEY',
            default => strtoupper($provider) . '_API_KEY',
        };
    }
}
