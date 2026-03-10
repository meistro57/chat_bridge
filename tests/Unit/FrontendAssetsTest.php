<?php

namespace Tests\Unit;

use App\Support\FrontendAssets;
use Tests\TestCase;

class FrontendAssetsTest extends TestCase
{
    public function test_it_reports_assets_present_when_manifest_exists(): void
    {
        $directory = sys_get_temp_dir().'/frontend-assets-'.uniqid('', true);
        mkdir($directory, 0777, true);
        $manifest = $directory.'/manifest.json';
        file_put_contents($manifest, '{}');

        $assets = new FrontendAssets($manifest, $directory.'/hot');

        $this->assertTrue($assets->hasViteAssets());

        unlink($manifest);
        rmdir($directory);
    }

    public function test_it_reports_assets_present_when_hot_file_exists(): void
    {
        $directory = sys_get_temp_dir().'/frontend-hot-'.uniqid('', true);
        mkdir($directory, 0777, true);
        $hotFile = $directory.'/hot';
        file_put_contents($hotFile, 'http://127.0.0.1:5173');

        $assets = new FrontendAssets($directory.'/manifest.json', $hotFile);

        $this->assertTrue($assets->hasViteAssets());

        unlink($hotFile);
        rmdir($directory);
    }

    public function test_it_reports_assets_missing_when_neither_manifest_nor_hot_file_exist(): void
    {
        $directory = sys_get_temp_dir().'/frontend-missing-'.uniqid('', true);
        mkdir($directory, 0777, true);

        $assets = new FrontendAssets($directory.'/manifest.json', $directory.'/hot');

        $this->assertFalse($assets->hasViteAssets());

        rmdir($directory);
    }
}
