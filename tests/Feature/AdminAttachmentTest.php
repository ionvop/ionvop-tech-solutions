<?php

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'admin.email' => 'ionvop@gmail.com',
        'chat.uploads.disk' => 'local',
    ]);

    Storage::fake('local');

    $this->admin = User::factory()->create(['email' => 'ionvop@gmail.com']);
});

test('ionvop can reply with an uploaded file', function () {
    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.conversations.messages.store', $conversation), [
            'message' => 'Here is the draft contract.',
            'file' => UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('message.type', 'file')
        ->assertJsonPath('message.attachment.kind', 'file')
        ->assertJsonPath('message.attachment.name', 'contract.pdf');

    $message = Message::query()->where('role', Message::ROLE_ADMIN)->sole();

    expect($message->attachment)->not->toBeNull();
    Storage::disk('local')->assertExists($message->attachment->path);
});

test('an admin reply with neither text nor a file is rejected', function () {
    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.conversations.messages.store', $conversation), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('a guest cannot upload to a conversation', function () {
    $conversation = Conversation::factory()->create();

    $this->post(route('admin.conversations.messages.store', $conversation), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertRedirect(route('admin.login'));
});

test('a non-admin cannot upload to a conversation', function () {
    $conversation = Conversation::factory()->create();
    $user = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($user)
        ->post(route('admin.conversations.messages.store', $conversation), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();
});

test('ionvop can stream any attachment', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create(['type' => Message::TYPE_VIDEO]);
    $attachment = Attachment::factory()->video()->for($message)->create();

    Storage::disk('local')->put($attachment->path, 'fake-video-bytes');

    $this->actingAs($this->admin)
        ->get(route('admin.attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('the admin transcript payload includes the attachment', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create([
        'type' => Message::TYPE_IMAGE,
        'body' => null,
    ]);

    Attachment::factory()->image()->for($message)->create(['original_name' => 'shop.jpg']);

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.attachment.kind', 'image')
        ->assertJsonPath('messages.0.attachment.name', 'shop.jpg');
});

test('the conversation list previews an upload by its file name', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create([
        'type' => Message::TYPE_IMAGE,
        'body' => null,
    ]);

    Attachment::factory()->image()->for($message)->create(['original_name' => 'storefront.jpg']);

    $this->actingAs($this->admin)
        ->getJson(route('admin.conversations.index'))
        ->assertOk()
        ->assertJsonPath('conversations.0.preview', '[image] storefront.jpg');
});
