<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [ChatController::class, 'index'])->name('dashboard');

Route::prefix('chat')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/search', [ChatController::class, 'search'])->name('chat.search');
    Route::get('/create', [ChatController::class, 'create'])->name('chat.create');
    Route::post('/store', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/{conversation}/stop', [ChatController::class, 'stop'])->name('chat.stop');
    Route::delete('/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::get('/{conversation}/transcript', [ChatController::class, 'transcript'])->name('chat.transcript');
});

use App\Http\Controllers\PersonaController;
Route::resource('personas', PersonaController::class);
