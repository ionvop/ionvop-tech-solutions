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
                ['type' => 'set_client_name', 'value' => 'Jomar'],
                ['type' => 'set_conversation_label', 'value' => 'Jomar — hardware store'],
            ],
        ])),
    ]);

    $response = $this->postJson(route('chat.messages.store'), [
        'message' => "I'm Jomar. We run a small hardware store in Nabunturan.",
    ]);

    $response->assertOk()
        ->assertJsonPath('error', null)
        ->assertJsonCount(3, 'messages')
        ->assertJsonPath('messages.0.role', 'assistant')
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

        return $request->url() === 'https://openrouter.test/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'test/model'
            && ($format['type'] ?? null) === 'json_schema'
            && ($format['json_schema']['strict'] ?? null) === true
            && ($request['provider']['require_parameters'] ?? null) === true;
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

test('a failed assistant call keeps the client message and reports an error', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(['error' => ['message' => 'Provider unavailable']], 503),
    ]);

    $response = $this->postJson(route('chat.messages.store'), ['message' => 'Hello there']);

    $response->assertStatus(502)
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.role', 'assistant')
        ->assertJsonPath('messages.1.role', 'client')
        ->assertJsonPath('messages.1.body', 'Hello there')
        ->assertJsonPath('error', fn (string $error): bool => str_contains($error, 'could not reply'));

    expect(Message::query()->where('role', Message::ROLE_CLIENT)->count())->toBe(1);
});

test('the assistant ignores actions it is not allowed to apply', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeCompletion([
            'reply' => 'Understood.',
            'actions' => [
                ['type' => 'set_stage', 'value' => 'development'],
                ['type' => 'set_client_name', 'value' => 'Jomar'],
            ],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Call me Jomar'])->assertOk();

    $conversation = Conversation::query()->sole();

    expect($conversation->client_name)->toBe('Jomar')
        ->and($conversation->stage)->toBe(Conversation::INITIAL_STAGE);
});
