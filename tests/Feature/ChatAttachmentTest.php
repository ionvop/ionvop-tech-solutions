<?php

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.timeout' => 10,
        'chat.uploads.disk' => 'local',
    ]);

    Storage::fake('local');
});

/**
 * Build a fake OpenRouter completion carrying the given structured payload.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function fakeUploadCompletion(array $payload): array
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

test('a client can upload an image with a caption', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeUploadCompletion([
            'reply' => 'Thanks for the photo. What does it show?',
            'actions' => [],
        ])),
    ]);

    $response = $this->post(route('chat.messages.store'), [
        'message' => 'Here is our current price list.',
        'file' => UploadedFile::fake()->image('prices.jpg', 640, 480),
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('messages.1.type', 'image')
        ->assertJsonPath('messages.1.body', 'Here is our current price list.')
        ->assertJsonPath('messages.1.attachment.kind', 'image')
        ->assertJsonPath('messages.1.attachment.name', 'prices.jpg');

    $message = Message::query()->where('role', Message::ROLE_CLIENT)->sole();
    $attachment = $message->attachment;

    expect($attachment)->not->toBeNull()
        ->and($attachment->kind)->toBe(Attachment::KIND_IMAGE)
        ->and($attachment->original_name)->toBe('prices.jpg')
        ->and($attachment->disk)->toBe('local');

    Storage::disk('local')->assertExists($attachment->path);
});

test('a client can upload a file without any text', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeUploadCompletion([
            'reply' => 'Got it.',
            'actions' => [],
        ])),
    ]);

    $this->post(route('chat.messages.store'), [
        'file' => UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('messages.1.type', 'file')
        ->assertJsonPath('messages.1.body', null)
        ->assertJsonPath('messages.1.attachment.kind', 'file');
});

test('a text-only message still works without a file', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeUploadCompletion([
            'reply' => 'Understood.',
            'actions' => [],
        ])),
    ]);

    $this->postJson(route('chat.messages.store'), ['message' => 'Just text.'])
        ->assertOk()
        ->assertJsonPath('messages.1.type', 'text')
        ->assertJsonPath('messages.1.attachment', null);
});

test('a message with neither text nor a file is rejected', function () {
    $this->postJson(route('chat.messages.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    expect(Conversation::query()->count())->toBe(0);
});

test('an unsupported file type is rejected', function () {
    $this->post(route('chat.messages.store'), [
        'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(Message::query()->count())->toBe(0);
});

test('an oversized file is rejected for its kind', function () {
    config(['chat.uploads.kinds.image.max_kb' => 1]);

    $this->post(route('chat.messages.store'), [
        'file' => UploadedFile::fake()->image('huge.png')->size(2048),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('the assistant is told about an upload but never receives the file', function () {
    Http::fake([
        'openrouter.test/*' => Http::response(fakeUploadCompletion([
            'reply' => 'Thanks.',
            'actions' => [],
        ])),
    ]);

    $this->post(route('chat.messages.store'), [
        'message' => 'This is our receipt.',
        'file' => UploadedFile::fake()->image('receipt.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $contents = collect($request['messages'])->pluck('content')->implode("\n");

        return str_contains($contents, 'The client uploaded an image named "receipt.jpg"')
            && str_contains($contents, 'You cannot open, read, watch, or listen to it.')
            && str_contains($contents, 'This is our receipt.');
    });
});

test('the transcript payload includes the attachment for polling', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create([
        'type' => Message::TYPE_AUDIO,
        'body' => null,
    ]);

    Attachment::factory()->audio()->for($message)->create([
        'original_name' => 'voice-note.mp3',
    ]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonPath('messages.0.attachment.kind', 'audio')
        ->assertJsonPath('messages.0.attachment.name', 'voice-note.mp3');
});

test('the owner can stream an attachment', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create(['type' => Message::TYPE_IMAGE]);
    $attachment = Attachment::factory()->image()->for($message)->create();

    Storage::disk('local')->put($attachment->path, 'fake-image-bytes');

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->get(route('chat.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('an attachment is hidden from a visitor without the conversation cookie', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create(['type' => Message::TYPE_IMAGE]);
    $attachment = Attachment::factory()->image()->for($message)->create();

    Storage::disk('local')->put($attachment->path, 'fake-image-bytes');

    $this->get(route('chat.attachments.show', $attachment))->assertNotFound();

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, 'someone-else')
        ->get(route('chat.attachments.show', $attachment))
        ->assertNotFound();
});

test('deleting a conversation removes its stored files', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create(['type' => Message::TYPE_IMAGE]);
    $attachment = Attachment::factory()->image()->for($message)->create();

    Storage::disk('local')->put($attachment->path, 'fake-image-bytes');
    Storage::disk('local')->assertExists($attachment->path);

    $conversation->delete();

    Storage::disk('local')->assertMissing($attachment->path);
    expect(Attachment::query()->count())->toBe(0);
});
