<?php

use App\Models\Attachment;
use App\Services\ChatUploads;

test('audio uploads are normalised to a canonical content type', function (string $extension, string $expected) {
    $uploads = new ChatUploads;

    expect($uploads->mimeTypeFor(Attachment::KIND_AUDIO, $extension, 'video/webm'))->toBe($expected);
})->with([
    'webm' => ['webm', 'audio/webm'],
    'm4a' => ['m4a', 'audio/mp4'],
    'ogg' => ['ogg', 'audio/ogg'],
    'mp3' => ['mp3', 'audio/mpeg'],
    'wav' => ['wav', 'audio/wav'],
]);

test('an unknown audio extension keeps the sniffed content type', function () {
    $uploads = new ChatUploads;

    expect($uploads->mimeTypeFor(Attachment::KIND_AUDIO, 'aac', 'audio/aac'))->toBe('audio/aac');
});

test('non-audio uploads keep the sniffed content type', function (string $kind) {
    $uploads = new ChatUploads;

    expect($uploads->mimeTypeFor($kind, 'webm', 'video/webm'))->toBe('video/webm');
})->with([
    'image' => [Attachment::KIND_IMAGE],
    'video' => [Attachment::KIND_VIDEO],
    'file' => [Attachment::KIND_FILE],
]);

test('the audio extension is matched case-insensitively', function () {
    $uploads = new ChatUploads;

    expect($uploads->mimeTypeFor(Attachment::KIND_AUDIO, 'WEBM', 'video/webm'))->toBe('audio/webm');
});
