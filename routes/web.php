<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/chat', [ChatController::class, 'index'])->name('chat');

Route::get('/chat/messages', [ChatController::class, 'messages'])
    ->middleware('throttle:chat-poll')
    ->name('chat.messages.index');

Route::post('/chat/messages', [ChatController::class, 'store'])
    ->middleware('throttle:chat')
    ->name('chat.messages.store');
Route::delete('/chat/conversation', [ChatController::class, 'forget'])
    ->middleware('throttle:chat')
    ->name('chat.conversation.forget');

Route::get('/chat/attachments/{attachment}', [AttachmentController::class, 'show'])
    ->middleware('throttle:chat-poll')
    ->name('chat.attachments.show');

Route::prefix('admin')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::get('auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('admin.auth.redirect');
    Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('admin.auth.callback');

    // Any signed-in user may sign out, so a non-admin is never trapped.
    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('admin.logout');

    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('admin');

        Route::get('conversations', [ConversationController::class, 'index'])->name('admin.conversations.index');
        Route::get('conversations/{conversation:reference}', [ConversationController::class, 'show'])
            ->name('admin.conversations.show');
        Route::patch('conversations/{conversation:reference}', [ConversationController::class, 'update'])
            ->name('admin.conversations.update');
        Route::delete('conversations/{conversation:reference}', [ConversationController::class, 'destroy'])
            ->name('admin.conversations.destroy');
        Route::post('conversations/{conversation:reference}/messages', [ConversationController::class, 'storeMessage'])
            ->name('admin.conversations.messages.store');
        Route::post('conversations/{conversation:reference}/summary', [ConversationController::class, 'summarize'])
            ->name('admin.conversations.summary');

        Route::get('attachments/{attachment}', [AttachmentController::class, 'admin'])
            ->name('admin.attachments.show');
    });
});
