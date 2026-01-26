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
        if (! $this->enabled) {
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

    public function shouldStopWithThreshold(string $text, array $stopWords, float $threshold): bool
    {
        $normalized = array_values(array_filter(array_map(function ($word) {
            $word = trim((string) $word);

            return $word === '' ? null : strtolower($word);
        }, $stopWords)));

        if ($normalized === []) {
            return false;
        }

        $threshold = max(0.1, min(1, $threshold));
        $text = strtolower($text);
        $matches = 0;

        foreach ($normalized as $word) {
            if (str_contains($text, $word)) {
                $matches++;
            }
        }

        return ($matches / count($normalized)) >= $threshold;
    }
}
