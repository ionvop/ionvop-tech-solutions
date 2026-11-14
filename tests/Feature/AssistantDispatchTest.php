<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'admin.email' => 'ionvop@gmail.com',
        'chat.ai_cooldown_seconds' => 10,
        'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.timeout' => 10,
    ]);
});

/**
 * Build a fake OpenRouter completion carrying the given structured payload.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function fakeDispatchCompletion(array $payload): array
{
    return [
        'id' => 'chatcmpl-test',
        'object' => 'chat.completion',
        'choices' => [
            [
                'index' => 0,
                'finish_reason' => 'stop',
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode($payload),
                ],
            ],
        ],
    ];
}

/**
 * Create a conversation whose client is waiting on the assistant.
 */
function waitingConversation(?DateTimeInterface $since = null): Conversation
{
    $conversation = Conversation::factory()->pendingForAssistant($since)->create();

    $message = $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We need a stock system.',
    ]);

    if ($since !== null) {
        $message->forceFill(['created_at' => $since])->save();
    }

    return $conversation;
}

test('a client message marks the conversation as waiting on the assistant', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'What should I call you?',
            'actions' => [],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Hello'])->assertOk();

    $conversation = Conversation::query()->sole();

    expect($conversation->ai_pending_since)->toBeNull();
});

test('the assistant answers at most one conversation per cooldown window', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'Thanks for waiting.',
            'actions' => [],
        ])),
    ]);

    $first = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $first->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($first->refresh()->ai_pending_since)->toBeNull();

    $second = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $second->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($second->refresh()->ai_pending_since)->not->toBeNull();

    Http::assertSentCount(1);
});

test('the cooldown expires after the configured number of seconds', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'Thanks for waiting.',
            'actions' => [],
        ])),
    ]);

    $first = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $first->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    $second = waitingConversation();

    $this->travel(11)->seconds();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $second->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($second->refresh()->ai_pending_since)->toBeNull();

    Http::assertSentCount(2);
});

test('the conversation that has waited longest is answered first', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'Thanks for waiting.',
            'actions' => [],
        ])),
    ]);

    $older = waitingConversation(now()->subMinutes(5));
    $newer = waitingConversation(now()->subMinute());

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $newer->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.role', 'client');

    expect($older->refresh()->ai_pending_since)->toBeNull()
        ->and($newer->refresh()->ai_pending_since)->not->toBeNull();
});

test('a silent turn still marks the conversation as processed', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => null,
            'actions' => [],
        ])),
    ]);

    $conversation = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($conversation->refresh()->ai_pending_since)->toBeNull();

    $other = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $other->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($other->refresh()->ai_pending_since)->not->toBeNull();
});

test('a reply from ionvop marks the conversation as processed', function () {
    $admin = User::factory()->create(['email' => 'ionvop@gmail.com']);
    $conversation = waitingConversation();

    $this->actingAs($admin)
        ->postJson(route('admin.conversations.messages.store', $conversation->reference), [
            'message' => 'I can build that.',
        ])
        ->assertCreated();

    expect($conversation->refresh()->ai_pending_since)->toBeNull();
});

test('a paused conversation is skipped by the scan', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'Thanks for waiting.',
            'actions' => [],
        ])),
    ]);

    $paused = waitingConversation(now()->subMinutes(10));
    $paused->forceFill(['ai_enabled' => false])->save();

    $active = waitingConversation(now()->subMinute());

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $active->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($active->refresh()->ai_pending_since)->toBeNull()
        ->and($paused->refresh()->ai_pending_since)->not->toBeNull();
});

test('a failed assistant call leaves the conversation waiting without starting a cooldown', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(['error' => ['message' => 'Provider unavailable']], 503),
    ]);

    $conversation = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($conversation->refresh()->ai_pending_since)->not->toBeNull();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect(Cache::has('chat:ai:cooldown'))->toBeFalse();
});

test('the assistant is told how long the client has been waiting', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'Sorry for the wait.',
            'actions' => [],
        ])),
    ]);

    $conversation = waitingConversation(now()->subMinutes(5));

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    Http::assertSent(function (Request $request): bool {
        $contents = implode("\n", array_column($request['messages'], 'content'));

        return str_contains($contents, 'Waiting on the assistant:')
            && str_contains($contents, '5 minutes ago')
            && preg_match('/That is 30\d seconds ago\./', $contents) === 1;
    });
});

test('a message that arrives while the assistant is thinking keeps its place in the queue', function () {
    $conversation = waitingConversation();

    Http::fake(function () use ($conversation) {
        $conversation->messages()->create([
            'role' => Message::ROLE_CLIENT,
            'type' => 'text',
            'body' => 'Are you still there?',
        ]);

        $conversation->forceFill(['ai_pending_since' => now()->addSecond()])->save();

        return Http::response(fakeDispatchCompletion([
            'reply' => 'Thanks for waiting.',
            'actions' => [],
        ]));
    });

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk();

    expect($conversation->refresh()->ai_pending_since)->not->toBeNull();
});

test('polling returns a reply the assistant wrote during the same request', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeDispatchCompletion([
            'reply' => 'Sorry for the wait. What should I call you?',
            'actions' => [],
        ])),
    ]);

    $conversation = waitingConversation();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.body', 'Sorry for the wait. What should I call you?');
});
