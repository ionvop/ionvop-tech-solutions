<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'admin.email' => 'ionvop@gmail.com',
        'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.timeout' => 10,
    ]);

    $this->admin = User::factory()->create(['email' => 'ionvop@gmail.com']);
});

/**
 * Build a fake OpenRouter completion carrying the given structured payload.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function fakeAdminCompletion(array $payload): array
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

test('the dashboard lists conversations for the administrator', function () {
    Conversation::factory()->withClient('Jomar', 'jomar@example.com')->create();

    $this->actingAs($this->admin)
        ->get(route('admin'))
        ->assertOk()
        ->assertSee('Jomar')
        ->assertSee('Project details')
        ->assertSee('ionvop@gmail.com');
});

test('the dashboard marks the selected conversation as read', function () {
    $conversation = Conversation::factory()->create([
        'admin_read_at' => null,
        'last_message_at' => now(),
    ]);

    expect($conversation->hasUnread())->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin'))->assertOk();

    expect($conversation->refresh()->admin_read_at)->not->toBeNull();
});

test('the conversation list can be searched and filtered', function () {
    Conversation::factory()->withClient('Jomar', 'jomar@example.com')->create();
    Conversation::factory()->withClient('Maria', 'maria@example.com')->create(['stage' => 'development']);

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.index', ['q' => 'Maria']))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.client_name', 'Maria');

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.index', ['stage' => 'development']))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.client_name', 'Maria');
});

test('the conversation list rejects an unknown stage filter', function () {
    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.index', ['stage' => 'nonsense']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stage');
});

test('a conversation can be opened with its transcript', function () {
    $conversation = Conversation::factory()->withClient('Jomar')->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We still write sales in a notebook.',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.client_name', 'Jomar')
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'We still write sales in a notebook.');
});

test('the transcript can be polled for messages newer than a cursor', function () {
    $conversation = Conversation::factory()->create();

    $first = $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'First message.',
    ]);

    $conversation->messages()->create([
        'role' => Message::ROLE_ADMIN,
        'type' => 'text',
        'body' => 'Second message.',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.show', [
            'conversation' => $conversation,
            'after_id' => $first->id,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'Second message.');
});

test('a self-paused message carries its reason in the transcript', function () {
    $conversation = Conversation::factory()->create(['ai_enabled' => false]);

    $conversation->messages()->create([
        'role' => Message::ROLE_ASSISTANT,
        'type' => 'text',
        'body' => Conversation::PAUSE_NOTICE,
        'meta' => ['reason' => 'Client is spamming'],
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.reason', 'Client is spamming');
});

test('the project details can be updated', function () {
    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson(route('admin.conversations.update', $conversation), [
            'label' => 'Jomar — hardware store',
            'client_name' => 'Jomar',
            'client_email' => 'jomar@example.com',
            'stage' => 'development',
            'currency' => 'PHP',
            'first_month_amount' => 2000,
            'monthly_amount' => 1000,
            'amount_paid' => 500,
            'amount_due' => 1500,
            'contract_status' => 'Active',
            'contract_started_at' => '2026-11-01',
            'contract_renews_at' => '2026-12-01',
            'ai_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('conversation.stage', 'development')
        ->assertJsonPath('conversation.ai_enabled', false);

    $conversation->refresh();

    expect($conversation->label)->toBe('Jomar — hardware store')
        ->and($conversation->stage)->toBe('development')
        ->and($conversation->amount_due)->toBe(1500)
        ->and($conversation->contract_status)->toBe('Active')
        ->and($conversation->ai_enabled)->toBeFalse();
});

test('the project details reject an unknown stage', function () {
    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson(route('admin.conversations.update', $conversation), [
            'stage' => 'nonsense',
            'currency' => 'PHP',
            'ai_enabled' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stage');
});

test('a reply from ionvop is stored without calling the assistant', function () {
    Http::fake();

    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.conversations.messages.store', $conversation), [
            'message' => 'Thanks Jomar, I will send a scope tomorrow.',
        ])
        ->assertCreated()
        ->assertJsonPath('message.role', Message::ROLE_ADMIN);

    expect($conversation->messages()->sole()->role)->toBe(Message::ROLE_ADMIN);

    Http::assertNothingSent();
});

test('a reply is required', function () {
    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.conversations.messages.store', $conversation), ['message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('a summary is generated and stored', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeAdminCompletion([
            'summary' => "Client: Jomar\nBusiness: Hardware store\nProblem: Sales are written in a notebook.",
        ])),
    ]);

    $conversation = Conversation::factory()->withClient('Jomar')->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We still write sales in a notebook.',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.conversations.summary', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.summary', "Client: Jomar\nBusiness: Hardware store\nProblem: Sales are written in a notebook.");

    expect($conversation->refresh()->summary_generated_at)->not->toBeNull();
});

test('a failed summary reports an error without storing anything', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.conversations.summary', $conversation))
        ->assertStatus(502);

    expect($conversation->refresh()->summary)->toBeNull();
});

test('a conversation can be deleted with its messages', function () {
    $conversation = Conversation::factory()->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'Hello',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson(route('admin.conversations.destroy', $conversation))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);
});

test('a non-admin cannot use the conversation endpoints', function () {
    $conversation = Conversation::factory()->create();
    $user = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($user)
        ->getJson(route('admin.conversations.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('admin.conversations.destroy', $conversation))
        ->assertForbidden();

    $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
});

test('the assistant history labels each human speaker', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeAdminCompletion([
            'reply' => 'Understood.',
            'actions' => [],
        ])),
    ]);

    $conversation = Conversation::factory()->create();

    $conversation->messages()->create([
        'role' => Message::ROLE_CLIENT,
        'type' => 'text',
        'body' => 'We need a stock system.',
    ]);

    $conversation->messages()->create([
        'role' => Message::ROLE_ADMIN,
        'type' => 'text',
        'body' => 'I can build that.',
    ]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->postJson(route('chat.messages.store'), ['message' => 'Great.'])
        ->assertOk();

    Http::assertSent(function (Request $request): bool {
        $contents = array_column($request['messages'], 'content');

        return in_array('[CLIENT]: We need a stock system.', $contents, true)
            && in_array('[IONVOP]: I can build that.', $contents, true)
            && in_array('[CLIENT]: Great.', $contents, true);
    });
});

test('a paused assistant does not reply to the client', function () {
    Http::fake();

    $conversation = Conversation::factory()->create(['ai_enabled' => false]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->postJson(route('chat.messages.store'), ['message' => 'Any update?'])
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.role', Message::ROLE_CLIENT)
        ->assertJsonPath('conversation.ai_enabled', false);

    Http::assertNothingSent();
});
