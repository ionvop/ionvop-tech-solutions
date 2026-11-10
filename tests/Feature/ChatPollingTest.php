<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('polling returns the transcript for the visitor conversation', function () {
    $conversation = Conversation::factory()->withClient('Jomar', 'jomar@example.com')->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We still write sales in a notebook.',
    ]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonPath('conversation.reference', $conversation->reference)
        ->assertJsonPath('conversation.client_name', 'Jomar')
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.role', 'client')
        ->assertJsonPath('messages.0.body', 'We still write sales in a notebook.');
});

test('polling returns an empty payload without a conversation cookie', function () {
    $this->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonPath('conversation', null)
        ->assertJsonCount(0, 'messages');
});

test('polling reflects a paused assistant', function () {
    $conversation = Conversation::factory()->create(['ai_enabled' => false]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonPath('conversation.ai_enabled', false);
});

test('polling ignores a cookie that points at a missing conversation', function () {
    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, 'does-not-exist')
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonPath('conversation', null)
        ->assertJsonCount(0, 'messages');
});
