<?php

use App\Http\Controllers\Api\ChatBridgeController;
use App\Http\Controllers\Api\McpController as ApiMcpController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\Admin\McpUtilitiesController;
use App\Http\Middleware\EnsureChatBridgeOrSanctumToken;
use App\Http\Middleware\EnsureSanctumToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Chat Bridge API: accepts shared env token (backward compat) OR personal Sanctum token
Route::post('/chat-bridge/respond', [ChatBridgeController::class, 'respond'])
    ->middleware(EnsureChatBridgeOrSanctumToken::class);

// MCP routes: personal Sanctum token required (user-specific context)
Route::middleware(EnsureSanctumToken::class)->group(function () {
    Route::prefix('mcp')->group(function () {
        Route::get('/health', [ApiMcpController::class, 'health']);
        Route::get('/stats', [ApiMcpController::class, 'stats']);
        Route::get('/recent-chats', [ApiMcpController::class, 'recentChats']);
        Route::get('/search-chats', [ApiMcpController::class, 'search']);
        Route::get('/contextual-memory', [ApiMcpController::class, 'contextualMemory']);
        Route::get('/contextual_memory', [ApiMcpController::class, 'contextualMemory']);
        Route::get('/conversation/{conversation}', [ApiMcpController::class, 'conversation']);
    });
    Route::post('/mcp', [McpController::class, 'handle']);
});

Route::prefix('admin/mcp-utilities')->middleware([EnsureSanctumToken::class, 'admin'])->group(function () {
    Route::get('/embeddings/compare', [McpUtilitiesController::class, 'compareEmbeddings']);
    Route::post('/embeddings/populate', [McpUtilitiesController::class, 'populateEmbeddings']);
    Route::post('/flush', [McpUtilitiesController::class, 'flush']);
    Route::get('/traffic', [McpUtilitiesController::class, 'traffic']);
});

// Provider API routes (no auth required — used by the Create Conversation UI)
Route::get('/providers/models', [\App\Http\Controllers\Api\ProviderController::class, 'getModels']);
