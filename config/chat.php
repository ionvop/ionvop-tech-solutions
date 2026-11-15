<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assistant Cooldown
    |--------------------------------------------------------------------------
    |
    | The number of seconds the assistant waits after answering a conversation
    | before it may answer another one. Every request that arrives while the
    | cooldown is running is served without calling the model, which keeps a
    | sudden influx of inquiries from spending the whole token budget at once.
    |
    */

    'ai_cooldown_seconds' => (int) env('CHAT_AI_COOLDOWN_SECONDS', 10),

    /*
    |--------------------------------------------------------------------------
    | Recordings
    |--------------------------------------------------------------------------
    |
    | Clients and ionvop can record a voice note in the composer instead of
    | picking a file. The browser stops recording on its own once this many
    | seconds have passed, which keeps a forgotten recording from growing
    | without bound. The audio upload ceiling still applies on top of it.
    |
    */

    'recordings' => [
        'max_seconds' => (int) env('CHAT_RECORDING_MAX_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Clients and ionvop can attach one file to a message. Files are stored on
    | the private disk below and streamed back through an authenticated route,
    | so nothing is reachable without the conversation cookie or an admin
    | session. Each kind lists the extensions it accepts and its size ceiling
    | in kilobytes.
    |
    */

    'uploads' => [

        'disk' => env('CHAT_UPLOAD_DISK', 'local'),

        'kinds' => [
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                'max_kb' => 10240,
            ],
            'audio' => [
                'extensions' => ['mp3', 'wav', 'ogg', 'm4a', 'webm'],
                'max_kb' => 25600,
            ],
            'video' => [
                'extensions' => ['mp4', 'webm', 'mov'],
                'max_kb' => 51200,
            ],
            'file' => [
                'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip'],
                'max_kb' => 10240,
            ],
        ],

    ],

];
