<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Resolves, validates, and stores files attached to chat messages.
 *
 * The allowed extensions and size ceilings live in `config('chat.uploads')` so
 * the client, the admin dashboard, and the assistant all agree on what a
 * message may carry.
 */
class ChatUploads
{
    /**
     * Get the configured upload disk.
     */
    public function disk(): string
    {
        return (string) config('chat.uploads.disk', 'local');
    }

    /**
     * Get the configured kinds, keyed by kind name.
     *
     * @return array<string, array{extensions: list<string>, max_kb: int}>
     */
    public function kinds(): array
    {
        return (array) config('chat.uploads.kinds', []);
    }

    /**
     * Resolve the kind a file belongs to from its extension.
     *
     * Returns null when the extension is not allowed for any kind.
     */
    public function kindFor(UploadedFile $file): ?string
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        if ($extension === '') {
            return null;
        }

        foreach ($this->kinds() as $kind => $rules) {
            if (in_array($extension, $rules['extensions'], true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * Get the size ceiling in kilobytes for a kind.
     */
    public function maxKilobytes(string $kind): int
    {
        return (int) ($this->kinds()[$kind]['max_kb'] ?? 0);
    }

    /**
     * Get every allowed extension, for the file picker's `accept` attribute.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        $extensions = [];

        foreach ($this->kinds() as $rules) {
            $extensions = [...$extensions, ...$rules['extensions']];
        }

        return array_values(array_unique($extensions));
    }

    /**
     * Store an uploaded file and record it against a message.
     *
     * The stored name is random so a client cannot influence the path, and the
     * original name is kept only as metadata for display.
     */
    public function store(Message $message, UploadedFile $file, string $kind): Attachment
    {
        $disk = $this->disk();
        $extension = Str::lower($file->getClientOriginalExtension());
        $name = Str::random(40).($extension === '' ? '' : '.'.$extension);
        $directory = 'chat/'.$message->conversation->reference;

        $path = $file->storeAs($directory, $name, ['disk' => $disk]);

        if (! is_string($path)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $message->attachment()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => Str::limit((string) $file->getMimeType(), 128, ''),
            'size' => $file->getSize(),
            'kind' => $kind,
        ]);
    }

    /**
     * Resolve the local filesystem path of a stored attachment.
     *
     * Returns null for disks that do not expose a local path, such as S3.
     */
    public function path(Attachment $attachment): ?string
    {
        try {
            return Storage::disk($attachment->disk)->path($attachment->path);
        } catch (Throwable) {
            return null;
        }
    }
}
