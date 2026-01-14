<?php

use App\Http\Controllers\Api\McpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('mcp')->group(function () {
    Route::get('/health', [McpController::class, 'health']);
    Route::get('/stats', [McpController::class, 'stats']);
    Route::get('/recent-chats', [McpController::class, 'recentChats']);
    Route::get('/search-chats', [McpController::class, 'search']);
    Route::get('/contextual-memory', [McpController::class, 'contextualMemory']);
    Route::get('/conversation/{conversation}', [McpController::class, 'conversation']);
});
