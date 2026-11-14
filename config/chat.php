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

];
