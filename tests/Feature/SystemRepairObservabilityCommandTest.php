<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemRepairObservabilityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_only_mode_reports_observability_health(): void
    {
        config()->set('app.debug', true);
        config()->set('debugbar.enabled', true);
        config()->set('telescope.enabled', true);

        if (! Route::has('debugbar.openhandler')) {
            Route::get('/_debugbar/open-test', fn () => response()->noContent())->name('debugbar.openhandler');
        }

        if (! Route::has('telescope')) {
            Route::get('/telescope-test', fn () => response()->noContent())->name('telescope');
        }

        if (! Schema::hasTable('telescope_entries')) {
            Schema::create('telescope_entries', function ($table) {
                $table->bigIncrements('sequence');
                $table->uuid('uuid')->nullable();
                $table->uuid('batch_id')->nullable();
                $table->string('type', 20)->nullable();
                $table->longText('content')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        $this->artisan('system:observability-repair --check-only')
            ->expectsOutput('Running in check-only mode (no state changes).')
            ->expectsOutput('Observability checks passed.')
            ->assertExitCode(0);
    }
}
