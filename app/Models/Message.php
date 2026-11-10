<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'role', 'type', 'body', 'meta'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * The role used for messages written by the client.
     */
    public const ROLE_CLIENT = 'client';

    /**
     * The role used for messages written by the assistant.
     */
    public const ROLE_ASSISTANT = 'assistant';

    /**
     * The role used for messages written by ionvop from the admin dashboard.
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * Get the conversation the message belongs to.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Determine whether the message was written by the client.
     */
    public function isFromClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    /**
     * Determine whether the message was written by ionvop.
     */
    public function isFromAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Determine whether the message was written by a human rather than the assistant.
     */
    public function isFromHuman(): bool
    {
        return $this->isFromClient() || $this->isFromAdmin();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
