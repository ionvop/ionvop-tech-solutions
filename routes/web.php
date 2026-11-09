<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/chat', [ChatController::class, 'index'])->name('chat');

Route::post('/chat/messages', [ChatController::class, 'store'])
    ->middleware('throttle:chat')
    ->name('chat.messages.store');

Route::view('/admin', 'admin')->name('admin');
