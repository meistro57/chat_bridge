<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\File;

class StopWordService
{
    protected array $stopWords = [];
    protected bool $enabled = false;

    public function __construct()
    {
        $rolesPath = base_path('../roles.json');
        if (File::exists($rolesPath)) {
            $rolesData = json_decode(File::get($rolesPath), true);
            $this->stopWords = $rolesData['stop_words'] ?? [];
            $this->enabled = $rolesData['stop_word_detection_enabled'] ?? false;
        }
    }

    public function shouldStop(string $text): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $text = strtolower($text);
        foreach ($this->stopWords as $word) {
            if (str_contains($text, strtolower($word))) {
                return true;
            }
        }

        return false;
    }
}
