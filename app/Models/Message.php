<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['conversation_id', 'role', 'type', 'body', 'meta'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * The type used for plain text messages.
     */
    public const TYPE_TEXT = 'text';

    /**
     * The type used for messages carrying an uploaded image.
     */
    public const TYPE_IMAGE = 'image';

    /**
     * The type used for messages carrying an uploaded audio file.
     */
    public const TYPE_AUDIO = 'audio';

    /**
     * The type used for messages carrying an uploaded video file.
     */
    public const TYPE_VIDEO = 'video';

    /**
     * The type used for messages carrying any other uploaded document.
     */
    public const TYPE_FILE = 'file';

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
     * Get the file uploaded with the message, if any.
     *
     * @return HasOne<Attachment, $this>
     */
    public function attachment(): HasOne
    {
        return $this->hasOne(Attachment::class);
    }

    /**
     * Determine whether the message carries an uploaded file.
     */
    public function hasAttachment(): bool
    {
        return $this->attachment !== null;
    }

    /**
     * Describe the message in one line for conversation lists.
     *
     * Uploaded files have no body of their own, so the attachment name stands in
     * for the text a preview would otherwise show.
     */
    public function previewText(): ?string
    {
        if (filled($this->body)) {
            return $this->body;
        }

        $attachment = $this->attachment;

        if ($attachment === null) {
            return null;
        }

        return '['.$attachment->kind.'] '.$attachment->original_name;
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

    /**
     * Remove the uploaded file before the database cascade drops its row.
     *
     * Deleting through the database bypasses model events, so the file has to
     * be removed here while the attachment row is still readable.
     */
    protected static function booted(): void
    {
        static::deleting(function (Message $message): void {
            $message->attachment?->deleteFile();
        });
    }
}
