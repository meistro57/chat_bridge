<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [ChatController::class, 'index'])->name('dashboard');

Route::prefix('chat')->group(function () {
    Route::get('/create', [ChatController::class, 'create'])->name('chat.create');
    Route::post('/store', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::get('/{conversation}/transcript', [ChatController::class, 'transcript'])->name('chat.transcript');
});
