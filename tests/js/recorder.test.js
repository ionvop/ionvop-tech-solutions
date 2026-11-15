import assert from 'node:assert/strict';
import test from 'node:test';

import { baseMimeType, extensionForMimeType, formatDuration, pickMimeType } from '../../resources/js/recorder.js';

test('picks the first container the browser supports', () => {
    const supported = new Set(['audio/webm', 'audio/mp4']);

    assert.equal(pickMimeType((type) => supported.has(type)), 'audio/webm');
});

test('prefers opus in webm when the browser supports it', () => {
    assert.equal(pickMimeType(() => true), 'audio/webm;codecs=opus');
});

test('falls back to mp4 for a browser that only records mp4', () => {
    assert.equal(pickMimeType((type) => type === 'audio/mp4'), 'audio/mp4');
});

test('returns null when no candidate is supported', () => {
    assert.equal(pickMimeType(() => false), null);
});

test('strips codec parameters from a mime type', () => {
    assert.equal(baseMimeType('audio/webm;codecs=opus'), 'audio/webm');
    assert.equal(baseMimeType('AUDIO/MP4'), 'audio/mp4');
    assert.equal(baseMimeType(undefined), '');
});

test('maps a container to the extension the server accepts', () => {
    assert.equal(extensionForMimeType('audio/webm;codecs=opus'), 'webm');
    assert.equal(extensionForMimeType('audio/webm'), 'webm');
    assert.equal(extensionForMimeType('audio/ogg;codecs=opus'), 'ogg');
    assert.equal(extensionForMimeType('audio/mp4'), 'm4a');
});

test('falls back to webm for an unknown container', () => {
    assert.equal(extensionForMimeType('audio/x-unknown'), 'webm');
    assert.equal(extensionForMimeType(''), 'webm');
});

test('formats a duration as m:ss', () => {
    assert.equal(formatDuration(0), '0:00');
    assert.equal(formatDuration(9), '0:09');
    assert.equal(formatDuration(65), '1:05');
    assert.equal(formatDuration(300), '5:00');
});

test('formats a negative or non-numeric duration as zero', () => {
    assert.equal(formatDuration(-5), '0:00');
    assert.equal(formatDuration(undefined), '0:00');
    assert.equal(formatDuration('nonsense'), '0:00');
});
