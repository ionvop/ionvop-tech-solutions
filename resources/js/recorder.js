/**
 * In-app audio recording for the chat composer.
 *
 * A finished recording is handed back as a `File`, so it travels through the
 * same staging and upload path as a file the visitor picked from disk. Nothing
 * here talks to the server: the caller decides what to do with the recording.
 *
 * This module deliberately owns no reactive state. Alpine wraps the component
 * object in a proxy, and writes to a raw object held in a closure would never
 * reach the proxy, so the elapsed counter would freeze. Instead the recorder
 * reports progress through callbacks and the component stores it.
 *
 * The native `MediaStream`, `MediaRecorder`, and chunk buffer stay in the
 * closure for the same reason: proxying a live media stream breaks it.
 */

/**
 * Recording containers to try, best first.
 *
 * Opus in WebM is what Chrome, Edge, and Firefox produce. Safari records MP4
 * and cannot play WebM back, so it is offered last.
 */
const MIME_CANDIDATES = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/ogg;codecs=opus',
    'audio/mp4',
];

/**
 * File extensions for the containers a browser may produce.
 */
const EXTENSIONS = {
    'audio/webm': 'webm',
    'audio/ogg': 'ogg',
    'audio/mp4': 'm4a',
    'audio/mpeg': 'mp3',
    'audio/wav': 'wav',
    'audio/x-wav': 'wav',
};

/**
 * Strip codec parameters from a mime type.
 */
export function baseMimeType(mimeType) {
    return String(mimeType ?? '').split(';')[0].trim().toLowerCase();
}

/**
 * Choose the first recording container the browser can produce.
 *
 * Returns null when none of the candidates are supported, which tells the
 * caller to let `MediaRecorder` pick its own default.
 */
export function pickMimeType(isTypeSupported) {
    for (const candidate of MIME_CANDIDATES) {
        if (isTypeSupported(candidate)) {
            return candidate;
        }
    }

    return null;
}

/**
 * Resolve the file extension for a recording container.
 */
export function extensionForMimeType(mimeType) {
    return EXTENSIONS[baseMimeType(mimeType)] ?? 'webm';
}

/**
 * Describe a number of seconds as `m:ss`.
 */
export function formatDuration(seconds) {
    const total = Math.max(0, Math.floor(Number(seconds) || 0));
    const minutes = Math.floor(total / 60);
    const remainder = total % 60;

    return `${minutes}:${String(remainder).padStart(2, '0')}`;
}

/**
 * Build a filename-safe timestamp for a recording.
 */
function timestamp() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return [
        now.getFullYear(),
        pad(now.getMonth() + 1),
        pad(now.getDate()),
        '-',
        pad(now.getHours()),
        pad(now.getMinutes()),
        pad(now.getSeconds()),
    ].join('');
}

/**
 * Turn a `getUserMedia` failure into something worth showing a visitor.
 */
function microphoneErrorMessage(exception) {
    switch (exception?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Microphone access was blocked. Allow it in your browser settings to record a voice note.';
        case 'NotFoundError':
        case 'OverconstrainedError':
            return 'No microphone was found on this device.';
        case 'NotReadableError':
            return 'The microphone is already in use by another app.';
        default:
            return 'The microphone could not be started.';
    }
}

/**
 * Check whether this browser can record audio at all.
 */
export function canRecordAudio() {
    return typeof navigator !== 'undefined'
        && typeof navigator.mediaDevices?.getUserMedia === 'function'
        && typeof window !== 'undefined'
        && typeof window.MediaRecorder === 'function';
}

/**
 * Create a recorder bound to one composer.
 *
 * @param {object} options
 * @param {number} [options.maxSeconds] Longest recording before it stops itself.
 * @param {(file: File) => void} [options.onStage] Called with the finished recording.
 * @param {(message: string) => void} [options.onError] Called with a human-readable failure.
 * @param {(seconds: number) => void} [options.onElapsed] Called once per second while recording.
 * @param {(recording: boolean) => void} [options.onRecordingChange] Called when recording starts or ends.
 */
export function createAudioRecorder({
    maxSeconds = 300,
    onStage,
    onError,
    onElapsed,
    onRecordingChange,
} = {}) {
    let stream = null;
    let recorder = null;
    let chunks = [];
    let ticker = null;
    let startedAt = 0;
    let discard = false;

    const supported = canRecordAudio();

    /**
     * Stop the microphone tracks so the browser's recording indicator clears.
     */
    function releaseStream() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    /**
     * Stop the elapsed-time ticker.
     */
    function stopTicker() {
        if (ticker !== null) {
            clearInterval(ticker);
            ticker = null;
        }
    }

    /**
     * Report a failure to the caller.
     */
    function fail(message) {
        onError?.(message);
    }

    /**
     * Advance the elapsed counter, stopping at the configured ceiling.
     */
    function tick() {
        const elapsed = Math.floor((Date.now() - startedAt) / 1000);

        onElapsed?.(elapsed);

        if (elapsed >= maxSeconds) {
            stop();
        }
    }

    /**
     * Turn the collected chunks into a file and hand it to the caller.
     */
    function finish() {
        const mimeType = baseMimeType(recorder?.mimeType) || 'audio/webm';
        const blob = new Blob(chunks, { type: mimeType });

        chunks = [];
        recorder = null;
        releaseStream();

        if (discard) {
            discard = false;

            return;
        }

        if (blob.size === 0) {
            fail('Nothing was recorded. Try again.');

            return;
        }

        const extension = extensionForMimeType(mimeType);

        onStage?.(new File([blob], `voice-note-${timestamp()}.${extension}`, { type: mimeType }));
    }

    /**
     * Start recording, asking for the microphone first.
     */
    async function start() {
        if (recorder !== null) {
            return;
        }

        if (! supported) {
            fail('Recording is not available in this browser.');

            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (exception) {
            fail(microphoneErrorMessage(exception));

            return;
        }

        const mimeType = pickMimeType((type) => MediaRecorder.isTypeSupported(type));

        try {
            recorder = mimeType === null
                ? new MediaRecorder(stream)
                : new MediaRecorder(stream, { mimeType });
        } catch {
            releaseStream();
            fail('Recording could not start. Try a different browser.');

            return;
        }

        chunks = [];
        discard = false;

        recorder.ondataavailable = (event) => {
            if (event.data !== null && event.data.size > 0) {
                chunks.push(event.data);
            }
        };

        recorder.onstop = finish;

        recorder.onerror = () => {
            discard = true;
            fail('The recording stopped unexpectedly.');
        };

        recorder.start();

        startedAt = Date.now();
        ticker = setInterval(tick, 1000);
        onElapsed?.(0);
        onRecordingChange?.(true);
    }

    /**
     * Finish recording and stage the result.
     */
    function stop() {
        if (recorder === null) {
            return;
        }

        stopTicker();
        onRecordingChange?.(false);

        // `onstop` fires asynchronously and builds the file from the chunks.
        recorder.stop();
    }

    /**
     * Abandon the recording without staging anything.
     */
    function cancel() {
        if (recorder === null) {
            return;
        }

        discard = true;
        stop();
    }

    /**
     * Toggle between starting and finishing a recording.
     */
    function toggle() {
        if (recorder === null) {
            start();
        } else {
            stop();
        }
    }

    /**
     * Release the microphone and drop any recording in progress.
     */
    function destroy() {
        stopTicker();

        if (recorder !== null) {
            discard = true;
            onRecordingChange?.(false);
            recorder.stop();
        }

        releaseStream();
    }

    return { supported, maxSeconds, start, stop, cancel, toggle, destroy };
}
