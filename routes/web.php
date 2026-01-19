<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Chat and Conversation Routes
use App\Http\Controllers\ChatController;
Route::middleware('auth')->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/create', [ChatController::class, 'create'])->name('chat.create');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/search', [ChatController::class, 'search'])->name('chat.search');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/stop', [ChatController::class, 'stop'])->name('chat.stop');
    Route::delete('/chat/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::get('/chat/{conversation}/transcript', [ChatController::class, 'transcript'])->name('chat.transcript');
});

// Persona Routes
use App\Http\Controllers\PersonaController;
Route::middleware('auth')->resource('personas', PersonaController::class);

// API Key Routes
use App\Http\Controllers\ApiKeyController;
Route::middleware('auth')->resource('api-keys', ApiKeyController::class);

// Admin Routes
use App\Http\Controllers\Admin\UserController;
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class);
});

require __DIR__.'/auth.php';
