<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
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
function fakeCompletion(array $payload): array
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

test('the chat page renders an empty conversation shell', function () {
    $this->get(route('chat'))
        ->assertOk()
        ->assertSee('Project conversation')
        ->assertSee('Project details')
        ->assertSee('Not started yet')
        ->assertSee('Describe your business problem')
        ->assertDontSee('Sample conversation for layout preview');
});

test('sending a message starts a conversation and stores the cookie', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'Nice to meet you, Jomar. Which part of the day-to-day takes the most time?',
            'actions' => [
                ['type' => 'set_value', 'key' => 'set_client_name', 'value' => 'Jomar'],
                ['type' => 'set_value', 'key' => 'set_conversation_label', 'value' => 'Jomar — hardware store'],
            ],
        ])),
    ]);

    $response = $this->postJson(route('chat.messages.store'), [
        'message' => "I'm Jomar. We run a small hardware store in Nabunturan.",
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'messages')
        ->assertJsonPath('messages.0.role', 'assistant')
        ->assertJsonPath('messages.0.body', 'Hi! Would you like me to handle your initial inquiries while ionvop is unavailable, or would you rather wait for ionvop to respond?')
        ->assertJsonPath('messages.1.role', 'client')
        ->assertJsonPath('messages.2.role', 'assistant')
        ->assertJsonPath('conversation.client_name', 'Jomar')
        ->assertJsonPath('conversation.label', 'Jomar — hardware store');

    $conversation = Conversation::query()->sole();

    expect($conversation->messages()->count())->toBe(3)
        ->and($conversation->messages()->where('role', Message::ROLE_ASSISTANT)->count())->toBe(2);

    $response->assertCookie(Conversation::COOKIE, $conversation->reference);
});

test('the assistant request uses the configured model and structured output', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'What should I call you?',
            'actions' => [],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Hello'])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $format = $request['response_format'] ?? [];
        $schema = $format['json_schema']['schema'] ?? [];

        return $request->url() === 'https://openrouter.test/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'test/model'
            && ($format['type'] ?? null) === 'json_schema'
            && ($format['json_schema']['strict'] ?? null) === true
            && ($schema['properties']['reply']['type'] ?? null) === ['string', 'null']
            && ($request['provider']['require_parameters'] ?? null) === true;
    });
});

test('the assistant receives debug instructions when debug mode is enabled', function () {
    config(['app.debug' => true]);

    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'What should I call you?',
            'actions' => [],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Hello'])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $systemPrompt = $request['messages'][0]['content'] ?? '';

        return str_contains($systemPrompt, 'You are the first-contact assistant for ionvop')
            && str_contains($systemPrompt, '## Debug mode')
            && ! str_contains($systemPrompt, '## Production mode');
    });
});

test('the assistant receives production instructions when debug mode is disabled', function () {
    config(['app.debug' => false]);

    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'What should I call you?',
            'actions' => [],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Hello'])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $systemPrompt = $request['messages'][0]['content'] ?? '';

        return str_contains($systemPrompt, 'You are the first-contact assistant for ionvop')
            && str_contains($systemPrompt, '## Production mode')
            && ! str_contains($systemPrompt, '## Debug mode');
    });
});

test('a returning visitor sees their stored conversation', function () {
    $conversation = Conversation::factory()->withClient('Jomar', 'jomar@example.com')->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We still write sales in a notebook.',
    ]);

    $this->withCookie(Conversation::COOKIE, $conversation->reference)
        ->get(route('chat'))
        ->assertOk()
        ->assertSee('Jomar')
        ->assertSee('jomar@example.com')
        ->assertSee('We still write sales in a notebook.');
});

test('a message is required and limited in length', function () {
    Http::fake();

    $this->postJson(route('chat.messages.store'), ['message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    $this->postJson(route('chat.messages.store'), ['message' => str_repeat('a', 2001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    Http::assertNothingSent();
});

test('forgetting a conversation clears the cookie but keeps the records', function () {
    $conversation = Conversation::factory()->withClient('Jomar', 'jomar@example.com')->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We still write sales in a notebook.',
    ]);

    $response = $this->withCookie(Conversation::COOKIE, $conversation->reference)
        ->deleteJson(route('chat.conversation.forget'));

    $response->assertOk()
        ->assertJsonPath('conversation.reference', null)
        ->assertJsonPath('conversation.label', 'Project conversation')
        ->assertJsonPath('conversation.stage', Conversation::INITIAL_STAGE)
        ->assertJsonPath('conversation.ai_enabled', true)
        ->assertJsonCount(0, 'messages')
        ->assertCookieExpired(Conversation::COOKIE);

    expect(Conversation::query()->whereKey($conversation->id)->exists())->toBeTrue()
        ->and($conversation->messages()->count())->toBe(1);
});

test('forgetting without a conversation cookie is harmless', function () {
    $this->deleteJson(route('chat.conversation.forget'))
        ->assertOk()
        ->assertJsonPath('conversation.reference', null)
        ->assertJsonCount(0, 'messages');

    expect(Conversation::query()->count())->toBe(0);
});

test('the chat page offers a way to start a new conversation', function () {
    $conversation = Conversation::factory()->create();

    $this->withCookie(Conversation::COOKIE, $conversation->reference)
        ->get(route('chat'))
        ->assertOk()
        ->assertSee('Danger zone')
        ->assertSee('Start a new conversation')
        ->assertSee('chat-reset-dialog');
});

test('a failed assistant call keeps the client message and leaves the turn pending', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(['error' => ['message' => 'Provider unavailable']], 503),
    ]);

    $response = $this->postJson(route('chat.messages.store'), ['message' => 'Hello there']);

    $response->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.role', 'assistant')
        ->assertJsonPath('messages.1.role', 'client')
        ->assertJsonPath('messages.1.body', 'Hello there')
        ->assertJsonMissingPath('error');

    $conversation = Conversation::query()->sole();

    expect(Message::query()->where('role', Message::ROLE_CLIENT)->count())->toBe(1)
        ->and($conversation->ai_pending_since)->not->toBeNull();
});

test('the assistant ignores actions it is not allowed to apply', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'Understood.',
            'actions' => [
                ['type' => 'set_stage', 'value' => 'development'],
                ['type' => 'set_value', 'key' => 'set_stage', 'value' => 'development'],
                ['type' => 'set_value', 'key' => 'set_client_name', 'value' => 'Jomar'],
            ],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Call me Jomar'])->assertOk();

    $conversation = Conversation::query()->sole();

    expect($conversation->client_name)->toBe('Jomar')
        ->and($conversation->stage)->toBe(Conversation::INITIAL_STAGE);
});

test('the assistant can stay silent without storing a message', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => null,
            'actions' => [],
        ])),
    ]);

    $response = $this->postJson(route('chat.messages.store'), ['message' => 'asdkjhasd']);

    $response->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.1.role', 'client');

    $conversation = Conversation::query()->sole();

    expect($conversation->messages()->where('role', Message::ROLE_ASSISTANT)->count())->toBe(1)
        ->and($conversation->ai_enabled)->toBeTrue();
});

test('the assistant can pause itself and the client sees a pause notice', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => null,
            'actions' => [
                ['type' => 'self_disable', 'reason' => 'Client is spamming'],
            ],
        ])),
    ]);

    $response = $this->postJson(route('chat.messages.store'), ['message' => 'buy now buy now buy now']);

    $response->assertOk()
        ->assertJsonPath('conversation.ai_enabled', false)
        ->assertJsonCount(3, 'messages')
        ->assertJsonPath('messages.2.role', 'assistant')
        ->assertJsonPath('messages.2.body', Conversation::PAUSE_NOTICE);

    $conversation = Conversation::query()->sole();
    $pause = $conversation->messages()->where('role', Message::ROLE_ASSISTANT)->latest('id')->firstOrFail();

    expect($conversation->ai_enabled)->toBeFalse()
        ->and($pause->body)->toBe(Conversation::PAUSE_NOTICE)
        ->and($pause->meta['reason'])->toBe('Client is spamming');
});

test('a reply is kept when the assistant pauses itself', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'I will step back and let ionvop take over.',
            'actions' => [
                ['type' => 'self_disable', 'reason' => 'Repeated off-topic messages'],
            ],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'what is the weather'])
        ->assertOk()
        ->assertJsonPath('conversation.ai_enabled', false)
        ->assertJsonPath('messages.2.body', 'I will step back and let ionvop take over.');

    $conversation = Conversation::query()->sole();

    expect($conversation->ai_enabled)->toBeFalse();
});

test('the assistant can summarize the conversation without pausing itself', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'Thanks, that is everything I need.',
            'actions' => [
                ['type' => 'generate_summary', 'summary' => "Client: Jomar\nBusiness: Hardware store"],
            ],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'That is all, thanks.'])
        ->assertOk()
        ->assertJsonPath('conversation.ai_enabled', true);

    $conversation = Conversation::query()->sole();

    expect($conversation->summary)->toBe("Client: Jomar\nBusiness: Hardware store")
        ->and($conversation->summary_generated_at)->not->toBeNull()
        ->and($conversation->ai_enabled)->toBeTrue();
});

test('a paused conversation does not call the assistant again', function () {
    Http::fake();

    $conversation = Conversation::factory()->create(['ai_enabled' => false]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->postJson(route('chat.messages.store'), ['message' => 'Hello again'])
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.role', 'client');

    Http::assertNothingSent();
});
