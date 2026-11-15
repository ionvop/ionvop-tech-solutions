import assert from 'node:assert/strict';
import test from 'node:test';

import { appendMessages, lastMessageId, reconcileVirtualGreeting } from '../../resources/js/transcript.js';

test('adopts the persisted greeting without rendering it twice', () => {
    const greeting = {
        id: 'virtual-greeting',
        renderKey: 'greeting',
        role: 'assistant',
        body: 'Welcome.',
        time: '',
        virtualGreeting: true,
    };
    const pending = { id: 'pending-1', role: 'client', pending: true };
    const messages = [greeting, pending];
    const responseMessages = [
        { id: 1, role: 'assistant', body: 'Welcome.', time: '9:00 AM' },
        { id: 2, role: 'client', body: 'Hello' },
        { id: 3, role: 'assistant', body: 'How can I help?' },
    ];

    messages.splice(messages.indexOf(pending), 1);
    const incoming = reconcileVirtualGreeting(messages, responseMessages, 'Welcome.');

    appendMessages(messages, incoming);

    assert.equal(messages.length, 3);
    assert.strictEqual(messages[0], greeting);
    assert.equal(messages[0].id, 1);
    assert.equal(messages[0].renderKey, 'greeting');
    assert.equal(messages[0].time, '9:00 AM');
    assert.equal(messages[0].virtualGreeting, false);
    assert.deepEqual(messages.map((message) => message.body), [
        'Welcome.',
        'Hello',
        'How can I help?',
    ]);
    assert.equal(lastMessageId(messages), 3);
});