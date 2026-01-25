<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\ProfileController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return \Inertia\Inertia::render('Dashboard', [
            'user' => auth()->user(),
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
    });

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Persona routes
    Route::resource('personas', PersonaController::class);

    // API Keys routes
    Route::resource('api-keys', \App\Http\Controllers\ApiKeyController::class);
    Route::post('/api-keys/{apiKey}/test', [\App\Http\Controllers\ApiKeyController::class, 'test'])->name('api-keys.test');

    // Analytics routes
    Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/query', [\App\Http\Controllers\AnalyticsController::class, 'query'])->name('analytics.query');
    Route::get('/analytics/export', [\App\Http\Controllers\AnalyticsController::class, 'export'])->name('analytics.export');

    // Chat routes
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/create', [ChatController::class, 'create'])->name('chat.create');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/search', [ChatController::class, 'search'])->name('chat.search');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/stop', [ChatController::class, 'stop'])->name('chat.stop');
    Route::delete('/chat/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::get('/chat/{conversation}/transcript', [ChatController::class, 'transcript'])->name('chat.transcript');

    // Transmission routes
    Route::get('/transmission', [\App\Http\Controllers\TransmissionController::class, 'index'])->name('transmission.index');
    Route::post('/transmission', [\App\Http\Controllers\TransmissionController::class, 'store'])->name('transmission.store');
});

require __DIR__.'/auth.php';
