<?php

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    Http::fake([
        'openrouter.test/*' => Http::response([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'finish_reason' => 'stop',
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['reply' => 'Got it.', 'actions' => []]),
                    ],
                ],
            ],
        ]),
    ]);
});

/**
 * Build a fake recording the way the browser would upload one.
 */
function fakeRecording(string $name, string $mimeType): UploadedFile
{
    return UploadedFile::fake()->create($name, 240, $mimeType);
}

test('a recorded webm voice note is accepted as audio', function () {
    $this->post(route('chat.messages.store'), [
        'file' => fakeRecording('voice-note-20261115-101500.webm', 'audio/webm'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('messages.1.type', 'audio')
        ->assertJsonPath('messages.1.attachment.kind', 'audio')
        ->assertJsonPath('messages.1.attachment.name', 'voice-note-20261115-101500.webm');

    $attachment = Message::query()->where('role', Message::ROLE_CLIENT)->sole()->attachment;

    expect($attachment->kind)->toBe(Attachment::KIND_AUDIO)
        ->and($attachment->mime_type)->toBe('audio/webm');

    Storage::disk('local')->assertExists($attachment->path);
});

test('a recorded m4a voice note is accepted as audio', function () {
    $this->post(route('chat.messages.store'), [
        'file' => fakeRecording('voice-note-20261115-101500.m4a', 'audio/mp4'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('messages.1.type', 'audio');

    $attachment = Message::query()->where('role', Message::ROLE_CLIENT)->sole()->attachment;

    expect($attachment->kind)->toBe(Attachment::KIND_AUDIO)
        ->and($attachment->mime_type)->toBe('audio/mp4');
});

test('a voice note can carry a caption', function () {
    $this->post(route('chat.messages.store'), [
        'message' => 'This is how we take orders today.',
        'file' => fakeRecording('voice-note-20261115-101500.webm', 'audio/webm'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('messages.1.type', 'audio')
        ->assertJsonPath('messages.1.body', 'This is how we take orders today.');
});

test('a recording longer than the audio ceiling is rejected', function () {
    config(['chat.uploads.kinds.audio.max_kb' => 1]);

    $this->post(route('chat.messages.store'), [
        'file' => fakeRecording('voice-note-20261115-101500.webm', 'audio/webm')->size(2048),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(Message::query()->count())->toBe(0);
});

test('a voice note is served back as audio rather than video', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create(['type' => Message::TYPE_AUDIO]);
    $attachment = Attachment::factory()->audio()->for($message)->create([
        'path' => 'chat/'.$conversation->reference.'/voice-note.webm',
        'original_name' => 'voice-note.webm',
        'mime_type' => 'audio/webm',
    ]);

    Storage::disk('local')->put($attachment->path, 'fake-audio-bytes');

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->get(route('chat.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/webm');
});

test('the transcript exposes a voice note as an audio attachment', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create([
        'type' => Message::TYPE_AUDIO,
        'body' => null,
    ]);

    Attachment::factory()->audio()->for($message)->create([
        'original_name' => 'voice-note-20261115-101500.webm',
        'mime_type' => 'audio/webm',
    ]);

    $this->withCredentials()
        ->withCookie(Conversation::COOKIE, $conversation->reference)
        ->getJson(route('chat.messages.index'))
        ->assertOk()
        ->assertJsonPath('messages.0.type', 'audio')
        ->assertJsonPath('messages.0.attachment.kind', 'audio')
        ->assertJsonPath('messages.0.attachment.mime', 'audio/webm');
});
