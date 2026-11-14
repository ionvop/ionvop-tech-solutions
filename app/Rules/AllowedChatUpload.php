<?php

namespace App\Rules;

use App\Services\ChatUploads;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates a chat upload against the configured kinds and size ceilings.
 *
 * Laravel's `mimes` rule trusts the file's contents, which rejects some valid
 * audio and video containers. Chat uploads are matched on extension instead,
 * and the file is always served back with a fixed content type and a sandbox
 * policy, so an unexpected payload cannot execute in the browser.
 */
class AllowedChatUpload implements ValidationRule
{
    public function __construct(private readonly ChatUploads $uploads) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be an uploaded file.');

            return;
        }

        $kind = $this->uploads->kindFor($value);

        if ($kind === null) {
            $fail('That file type is not supported. Attach an image, audio, video, or document.');

            return;
        }

        $maxKilobytes = $this->uploads->maxKilobytes($kind);

        if ($maxKilobytes > 0 && $value->getSize() > $maxKilobytes * 1024) {
            $fail('That file is too large. The limit for this file type is '.$this->humanLimit($maxKilobytes).'.');
        }
    }

    /**
     * Describe a kilobyte ceiling for humans.
     */
    private function humanLimit(int $kilobytes): string
    {
        if ($kilobytes < 1024) {
            return $kilobytes.' KB';
        }

        return round($kilobytes / 1024, 1).' MB';
    }
}
