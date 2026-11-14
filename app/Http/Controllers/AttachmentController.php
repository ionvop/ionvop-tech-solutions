<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Services\ChatUploads;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    /**
     * Stream an attachment to the client that owns the conversation.
     *
     * Files live on a private disk, so the conversation cookie is the only
     * thing that grants access. A wrong or missing cookie is reported as a
     * missing file rather than a forbidden one, so the route does not confirm
     * that an attachment exists.
     */
    public function show(Request $request, Attachment $attachment, ChatUploads $uploads): BinaryFileResponse
    {
        $reference = $request->cookie(Conversation::COOKIE);

        abort_unless(
            is_string($reference) && $reference !== '' && $attachment->message->conversation->reference === $reference,
            404,
        );

        return $this->stream($attachment, $uploads);
    }

    /**
     * Stream an attachment to a signed-in admin.
     *
     * Access is already limited by the `auth` and `admin` middleware on the
     * route, so any attachment may be opened here.
     */
    public function admin(Attachment $attachment, ChatUploads $uploads): BinaryFileResponse
    {
        return $this->stream($attachment, $uploads);
    }

    /**
     * Build the file response for an attachment.
     *
     * `response()->file()` is used instead of the storage disk's own response
     * helper because it supports range requests, which audio and video players
     * need in order to seek.
     */
    private function stream(Attachment $attachment, ChatUploads $uploads): BinaryFileResponse
    {
        $path = $uploads->path($attachment);

        abort_if($path === null || ! is_file($path), 404);

        return response()
            ->file($path, [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => $attachment->isDocument()
                    ? 'attachment; filename="'.$this->safeName($attachment->original_name).'"'
                    : 'inline; filename="'.$this->safeName($attachment->original_name).'"',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ])
            // `response()->file()` marks the response public by default, which
            // would let a shared proxy cache a private conversation's file.
            ->setPrivate()
            ->setMaxAge(3600);
    }

    /**
     * Strip characters that would break the Content-Disposition header.
     */
    private function safeName(string $name): string
    {
        return str_replace(['"', '\\', "\r", "\n"], '', $name);
    }
}
