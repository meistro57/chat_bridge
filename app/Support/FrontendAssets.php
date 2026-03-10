<?php

namespace App\Support;

class FrontendAssets
{
    public function __construct(
        private readonly ?string $manifestPath = null,
        private readonly ?string $hotFilePath = null,
    ) {}

    public function hasViteAssets(): bool
    {
        return is_file($this->resolvedHotFilePath()) || is_file($this->resolvedManifestPath());
    }

    private function resolvedManifestPath(): string
    {
        return $this->manifestPath ?? public_path('build/manifest.json');
    }

    private function resolvedHotFilePath(): string
    {
        return $this->hotFilePath ?? public_path('hot');
    }
}
