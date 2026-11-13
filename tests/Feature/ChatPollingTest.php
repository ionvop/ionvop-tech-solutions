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

test('polling with a cursor returns only messages that are not rendered yet', function () {
    $conversation = Conversation::factory()->create();

    $first = $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'First message.',
    ]);

    $conversation->messages()->create([
        'role' => Message::ROLE_ASSISTANT,
        'type' => 'text',
        'body' => 'Second message.',
    ]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index', ['after_id' => $first->id]))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'Second message.');
});

test('polling with a cursor past the newest message returns nothing', function () {
    $conversation = Conversation::factory()->create();

    $message = $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'Only message.',
    ]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index', ['after_id' => $message->id]))
        ->assertOk()
        ->assertJsonCount(0, 'messages');
});

test('polling rejects a cursor that is not a number', function () {
    $conversation = Conversation::factory()->create();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index', ['after_id' => 'nonsense']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('after_id');
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
