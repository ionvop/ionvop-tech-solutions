/**
 * Helpers for keeping a rendered transcript in sync with the server without
 * re-rendering the messages that are already on screen.
 *
 * Polling used to replace the whole message array, which re-ran every Alpine
 * `x-for` scope and rebuilt the text nodes. That dropped any text the visitor
 * had selected and could overwrite a message that was still in flight. These
 * helpers append only what is new and update objects in place.
 */

/**
 * Get the highest persisted message id in a list.
 *
 * Optimistic messages use string ids such as `pending-1699999999999`, so they
 * are ignored and never used as a polling cursor.
 */
export function lastMessageId(messages) {
    let highest = 0;

    for (const message of messages) {
        if (typeof message.id === 'number' && message.id > highest) {
            highest = message.id;
        }
    }

    return highest;
}

/**
 * Append messages that are not rendered yet.
 *
 * Optimistic messages are kept at the end of the list so a confirmed message
 * always lands above them.
 *
 * @returns {number} The number of messages that were appended.
 */
export function appendMessages(messages, incoming) {
    if (! Array.isArray(incoming) || incoming.length === 0) {
        return 0;
    }

    const known = new Set(messages.map((message) => message.id));
    const appended = [];

    for (const message of incoming) {
        if (message === null || message === undefined || known.has(message.id)) {
            continue;
        }

        known.add(message.id);
        appended.push(message);
    }

    if (appended.length === 0) {
        return 0;
    }

    const firstPending = messages.findIndex((message) => message.pending === true);

    if (firstPending === -1) {
        messages.push(...appended);
    } else {
        messages.splice(firstPending, 0, ...appended);
    }

    return appended.length;
}

/**
 * Copy changed fields onto an existing object.
 *
 * Keeping the same object reference stops Alpine from re-rendering bindings
 * that did not actually change.
 */
export function mergeFields(target, incoming) {
    if (! target || ! incoming) {
        return target;
    }

    for (const [key, value] of Object.entries(incoming)) {
        if (target[key] !== value) {
            target[key] = value;
        }
    }

    return target;
}

/**
 * Replace every item in a list without spreading the replacement.
 *
 * Spreading a large transcript into `splice` can exceed the engine's argument
 * limit, so the list is emptied and refilled instead.
 */
export function replaceAll(current, incoming) {
    current.splice(0, current.length);

    for (const item of incoming ?? []) {
        current.push(item);
    }

    return current;
}

/**
 * Merge a refreshed list into an existing one, reusing objects by key.
 *
 * Field changes are applied to the existing objects, so the array itself is
 * only rebuilt when the order or membership changed. That keeps the rendered
 * list stable across a background refresh.
 */
export function mergeList(current, incoming, keyOf) {
    if (! Array.isArray(incoming)) {
        return current;
    }

    const existing = new Map(current.map((item) => [keyOf(item), item]));

    const merged = incoming.map((item) => {
        const previous = existing.get(keyOf(item));

        return previous === undefined ? item : mergeFields(previous, item);
    });

    const unchanged = merged.length === current.length
        && merged.every((item, index) => item === current[index]);

    if (unchanged) {
        return current;
    }

    return replaceAll(current, merged);
}

/**
 * Check whether a scroll container is close enough to the bottom that new
 * messages should follow the viewport.
 */
export function isNearBottom(element, threshold = 80) {
    if (! element) {
        return true;
    }

    return element.scrollHeight - element.scrollTop - element.clientHeight <= threshold;
}
