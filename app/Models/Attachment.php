<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['message_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'kind'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * The kind used for uploaded images.
     */
    public const KIND_IMAGE = 'image';

    /**
     * The kind used for uploaded audio files.
     */
    public const KIND_AUDIO = 'audio';

    /**
     * The kind used for uploaded video files.
     */
    public const KIND_VIDEO = 'video';

    /**
     * The kind used for any other uploaded document.
     */
    public const KIND_FILE = 'file';

    /**
     * Get the message the attachment belongs to.
     *
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Determine whether the attachment is an image.
     */
    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE;
    }

    /**
     * Determine whether the attachment is an audio file.
     */
    public function isAudio(): bool
    {
        return $this->kind === self::KIND_AUDIO;
    }

    /**
     * Determine whether the attachment is a video file.
     */
    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    /**
     * Determine whether the attachment is a document rather than media.
     */
    public function isDocument(): bool
    {
        return $this->kind === self::KIND_FILE;
    }

    /**
     * Describe the attachment size for humans.
     */
    public function humanSize(): string
    {
        $size = (int) $this->size;

        if ($size < 1024) {
            return $size.' B';
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / (1024 * 1024), 1).' MB';
    }

    /**
     * Describe the attachment kind in a sentence, for the assistant transcript.
     */
    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_IMAGE => 'an image',
            self::KIND_AUDIO => 'an audio file',
            self::KIND_VIDEO => 'a video',
            default => 'a file',
        };
    }

    /**
     * Remove the stored file when the attachment row is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment): void {
            $attachment->deleteFile();
        });
    }

    /**
     * Delete the underlying file from its disk.
     */
    public function deleteFile(): void
    {
        if ($this->disk === '' || $this->path === '') {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
