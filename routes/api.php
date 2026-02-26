<?php

use App\Http\Controllers\Api\ChatBridgeController;
use App\Http\Controllers\Api\McpController as ApiMcpController;
use App\Http\Controllers\McpController;
use App\Http\Middleware\EnsureChatBridgeToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/chat-bridge/respond', [ChatBridgeController::class, 'respond'])
    ->middleware(EnsureChatBridgeToken::class);

Route::prefix('mcp')->group(function () {
    Route::get('/health', [ApiMcpController::class, 'health']);
    Route::get('/stats', [ApiMcpController::class, 'stats']);
    Route::get('/recent-chats', [ApiMcpController::class, 'recentChats']);
    Route::get('/search-chats', [ApiMcpController::class, 'search']);
    Route::get('/contextual-memory', [ApiMcpController::class, 'contextualMemory']);
    Route::get('/conversation/{conversation}', [ApiMcpController::class, 'conversation']);
});
Route::post('/mcp', [McpController::class, 'handle']);

// Provider API routes (no auth required for model listing)
Route::get('/providers/models', [\App\Http\Controllers\Api\ProviderController::class, 'getModels']);
