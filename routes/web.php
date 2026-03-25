<?php

use App\Http\Controllers\Admin\BoostDashboardController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\McpUtilitiesController;
use App\Http\Controllers\Admin\RedisDashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TranscriptChatController;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('/_boost/browser-logs', function (Request $request) {
    Log::channel('daily')->error('Browser Error', [
        'error' => $request->input('error'),
        'stack' => $request->input('stack'),
        'url' => $request->input('url'),
    ]);

    return response()->json(['status' => 'logged']);
});

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified']);

Route::middleware(['auth'])->group(function () {
    // Profile routes (must be accessible even when email becomes unverified)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::patch('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $hasOpenAiKey = ApiKey::where('user_id', auth()->id())
            ->where('provider', 'openai')
            ->where('is_active', true)
            ->exists();

        return \Inertia\Inertia::render('Dashboard', [
            'user' => auth()->user(),
            'hasOpenAiKey' => $hasOpenAiKey,
        ]);
    })->name('dashboard');
    // Admin routes
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);
        Route::get('/system', [\App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system');
        Route::post('/system/diagnostic', [\App\Http\Controllers\Admin\SystemController::class, 'runDiagnostic'])->name('system.diagnostic');
        Route::post('/system/openai-key', [\App\Http\Controllers\Admin\SystemController::class, 'updateOpenAiKey'])->name('system.openai-key');
        Route::post('/system/openai-key/test', [\App\Http\Controllers\Admin\SystemController::class, 'testOpenAiKey'])->name('system.openai-key.test');
        Route::post('/system/openai-key/clear', [\App\Http\Controllers\Admin\SystemController::class, 'clearOpenAiKey'])->name('system.openai-key.clear');
        Route::post('/system/embeddings-key', [\App\Http\Controllers\Admin\SystemController::class, 'updateEmbeddingsKey'])->name('system.embeddings-key');
        Route::post('/system/embeddings-key/test', [\App\Http\Controllers\Admin\SystemController::class, 'testEmbeddingsKey'])->name('system.embeddings-key.test');
        Route::post('/system/embeddings-key/clear', [\App\Http\Controllers\Admin\SystemController::class, 'clearEmbeddingsKey'])->name('system.embeddings-key.clear');
        Route::post('/system/maintenance-banner', [\App\Http\Controllers\Admin\SystemController::class, 'updateMaintenanceBanner'])->name('system.maintenance-banner');
        Route::get('/database/backup', [DatabaseController::class, 'backup'])->name('database.backup');
        Route::post('/database/backup/run', [DatabaseController::class, 'runBackup'])->name('database.backup.run');
        Route::get('/database/restore', [DatabaseController::class, 'restore'])->name('database.restore');
        Route::post('/database/restore', [DatabaseController::class, 'restoreRun'])->name('database.restore.run');
        Route::get('/database/backups/{filename}/download', [DatabaseController::class, 'download'])->name('database.backups.download');
        Route::delete('/database/backups', [DatabaseController::class, 'delete'])->name('database.backups.delete');
        Route::get('/boost', [BoostDashboardController::class, 'index'])->name('boost.dashboard');
        Route::get('/boost/stats', [BoostDashboardController::class, 'stats'])->name('boost.stats');
        Route::get('/redis', [RedisDashboardController::class, 'index'])->name('redis.index');
        Route::get('/redis/stats', [RedisDashboardController::class, 'stats'])->name('redis.stats');
        Route::get('/mcp-utilities', [McpUtilitiesController::class, 'index'])->name('mcp.utilities');
        Route::get('/mcp-utilities/embeddings/compare', [McpUtilitiesController::class, 'compareEmbeddings'])->name('mcp.utilities.embeddings.compare');
        Route::post('/mcp-utilities/embeddings/populate', [McpUtilitiesController::class, 'populateEmbeddings'])->name('mcp.utilities.embeddings.populate');
        Route::post('/mcp-utilities/flush', [McpUtilitiesController::class, 'flush'])->name('mcp.utilities.flush');
        Route::get('/mcp-utilities/traffic', [McpUtilitiesController::class, 'traffic'])->name('mcp.utilities.traffic');
        Route::get('/performance', [\App\Http\Controllers\Admin\PerformanceMonitorController::class, 'index'])->name('performance.index');
        Route::get('/performance/stats', [\App\Http\Controllers\Admin\PerformanceMonitorController::class, 'stats'])->name('performance.stats');
    });

    // Persona routes
    Route::post('/personas/generate', [PersonaController::class, 'generate'])->name('personas.generate');
    Route::patch('/personas/favorites/clear', [PersonaController::class, 'clearFavorites'])->name('personas.favorites.clear');
    Route::patch('/personas/{persona}/favorite', [PersonaController::class, 'toggleFavorite'])->name('personas.favorite');
    Route::resource('personas', PersonaController::class);

    // Template routes
    Route::patch('/templates/favorites/clear', [\App\Http\Controllers\ConversationTemplateController::class, 'clearFavorites'])->name('templates.favorites.clear');
    Route::patch('/templates/{template}/favorite', [\App\Http\Controllers\ConversationTemplateController::class, 'toggleFavorite'])->name('templates.favorite');
    Route::resource('templates', \App\Http\Controllers\ConversationTemplateController::class);
    Route::post('/templates/{template}/use', [\App\Http\Controllers\ConversationTemplateController::class, 'use'])->name('templates.use');
    Route::post('/templates/{template}/clone', [\App\Http\Controllers\ConversationTemplateController::class, 'clone'])->name('templates.clone');
    Route::post('/templates/save-from-chat', [\App\Http\Controllers\ConversationTemplateController::class, 'storeFromChat'])->name('templates.storeFromChat');

    // AI provider API keys
    Route::resource('api-keys', \App\Http\Controllers\ApiKeyController::class);
    Route::post('/api-keys/{apiKey}/test', [\App\Http\Controllers\ApiKeyController::class, 'test'])->name('api-keys.test');

    // Personal access tokens (for Chat Bridge API access)
    Route::get('/personal-tokens', [\App\Http\Controllers\PersonalAccessTokenController::class, 'index'])->name('personal-tokens.index');
    Route::post('/personal-tokens', [\App\Http\Controllers\PersonalAccessTokenController::class, 'store'])->name('personal-tokens.store');
    Route::delete('/personal-tokens/{personalAccessToken}', [\App\Http\Controllers\PersonalAccessTokenController::class, 'destroy'])->name('personal-tokens.destroy');

    // Analytics routes
    Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/query', [\App\Http\Controllers\AnalyticsController::class, 'query'])->name('analytics.query');
    Route::post('/analytics/query/run-sql', [\App\Http\Controllers\AnalyticsController::class, 'runSql'])->name('analytics.query.run-sql');
    Route::get('/analytics/metrics', [\App\Http\Controllers\AnalyticsController::class, 'metrics'])->name('analytics.metrics');
    Route::post('/analytics/export', [\App\Http\Controllers\AnalyticsController::class, 'export'])->name('analytics.export');
    Route::delete('/analytics/history', [\App\Http\Controllers\AnalyticsController::class, 'clearHistory'])->name('analytics.history.clear');

    // Chat routes
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/create', [ChatController::class, 'create'])->name('chat.create');
    Route::get('/chat/live-status', [ChatController::class, 'liveStatus'])->name('chat.live-status');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store')->middleware('throttle:ai-chat-create');
    Route::get('/chat/search', [ChatController::class, 'search'])->name('chat.search');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/stop', [ChatController::class, 'stop'])->name('chat.stop');
    Route::post('/chat/{conversation}/resume', [ChatController::class, 'resume'])->name('chat.resume');
    Route::post('/chat/{conversation}/retry-with', [ChatController::class, 'retryWith'])->name('chat.retry-with');
    Route::delete('/chat/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::get('/chat/{conversation}/transcript', [ChatController::class, 'transcript'])->name('chat.transcript');

    // Transcript Chat (AI Q&A over embeddings)
    Route::get('/transcript-chat', [TranscriptChatController::class, 'index'])->name('transcript-chat.index');
    Route::post('/transcript-chat/ask', [TranscriptChatController::class, 'ask'])->name('transcript-chat.ask');

    // OpenRouter stats
    Route::get('/openrouter/stats', [\App\Http\Controllers\OpenRouterController::class, 'stats'])->name('openrouter.stats');

    // Transmission routes
    Route::get('/transmission', [\App\Http\Controllers\TransmissionController::class, 'index'])->name('transmission.index');
    Route::post('/transmission', [\App\Http\Controllers\TransmissionController::class, 'store'])->name('transmission.store');

    // Orchestrator routes
    Route::prefix('orchestrator')->name('orchestrator.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OrchestratorController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\OrchestratorController::class, 'store'])->name('store');
        Route::get('/wizard', [\App\Http\Controllers\OrchestratorWizardController::class, 'show'])->name('wizard');
        Route::post('/wizard/chat', [\App\Http\Controllers\OrchestratorWizardController::class, 'chat'])->name('wizard.chat');
        Route::post('/wizard/materialize', [\App\Http\Controllers\OrchestratorWizardController::class, 'materialize'])->name('wizard.materialize');
        Route::get('/{orchestration}', [\App\Http\Controllers\OrchestratorController::class, 'show'])->name('show');
        Route::put('/{orchestration}', [\App\Http\Controllers\OrchestratorController::class, 'update'])->name('update');
        Route::delete('/{orchestration}', [\App\Http\Controllers\OrchestratorController::class, 'destroy'])->name('destroy');
        Route::post('/{orchestration}/run', [\App\Http\Controllers\OrchestratorController::class, 'run'])->name('run');
        Route::post('/{orchestration}/pause', [\App\Http\Controllers\OrchestratorController::class, 'pause'])->name('pause');
        Route::post('/runs/{run}/resume', [\App\Http\Controllers\OrchestratorController::class, 'resume'])->name('resume');
        Route::get('/{orchestration}/runs', [\App\Http\Controllers\OrchestratorRunController::class, 'index'])->name('runs.index');
        Route::get('/runs/{run}', [\App\Http\Controllers\OrchestratorRunController::class, 'show'])->name('runs.show');
    });
});

require __DIR__.'/auth.php';
