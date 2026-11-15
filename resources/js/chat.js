import { appendMessages, isNearBottom, lastMessageId, mergeFields, reconcileVirtualGreeting, replaceAll } from './transcript';

const POLL_MS = 10_000;

export default function chat(initial) {
    return {
        messages: initial.messages ?? [],
        conversation: initial.conversation ?? null,
        emptyConversation: initial.emptyConversation ?? null,
        stages: initial.stages ?? [],
        greeting: initial.greeting ?? '',
        endpoint: initial.endpoint ?? '',
        pollEndpoint: initial.pollEndpoint ?? '',
        forgetEndpoint: initial.forgetEndpoint ?? '',
        uploadKinds: initial.uploadKinds ?? {},

        draft: '',
        pendingFile: null,
        pendingPreview: null,
        uploadProgress: 0,
        lightbox: null,
        sending: false,
        resetting: false,
        error: null,
        polling: true,
        pollInFlight: false,
        timers: [],
        epoch: 0,

        init() {
            this.messages = this.messagesWithGreeting(this.messages);
            this.scrollToBottom();

            this.timers.push(setInterval(() => this.poll(), POLL_MS));

            document.addEventListener('visibilitychange', () => {
                this.polling = ! document.hidden;
            });
        },

        destroy() {
            this.timers.forEach((timer) => clearInterval(timer));
            this.timers = [];
            this.releasePreview();
        },

        get canSend() {
            return (this.draft.trim().length > 0 || this.pendingFile !== null)
                && ! this.sending
                && ! this.resetting;
        },

        /**
         * Build the file picker's accept list from the configured kinds.
         */
        get accept() {
            const extensions = Object.values(this.uploadKinds)
                .flatMap((kind) => kind.extensions ?? [])
                .map((extension) => `.${extension}`);

            return extensions.join(',');
        },

        /**
         * Open the hidden file picker.
         *
         * The photo button opens a picker limited to images; the attach button
         * opens one that accepts every configured kind.
         */
        pickFile(kind = 'any') {
            if (this.sending || this.resetting) {
                return;
            }

            const input = kind === 'image' ? this.$refs.photoInput : this.$refs.fileInput;

            input?.click();
        },

        /**
         * Validate and stage a file the visitor picked.
         *
         * The server repeats every check; this only exists so an obviously
         * wrong file is rejected before it is uploaded.
         */
        onFileSelected(event) {
            const file = event.target?.files?.[0] ?? null;

            event.target.value = '';

            if (file === null) {
                return;
            }

            const extension = (file.name.split('.').pop() ?? '').toLowerCase();
            const kind = Object.entries(this.uploadKinds)
                .find(([, rules]) => (rules.extensions ?? []).includes(extension));

            if (kind === undefined) {
                this.error = 'That file type is not supported. Attach an image, audio, video, or document.';

                return;
            }

            const maxKilobytes = kind[1].max_kb ?? 0;

            if (maxKilobytes > 0 && file.size > maxKilobytes * 1024) {
                this.error = `That file is too large. The limit for this file type is ${this.humanSize(maxKilobytes * 1024)}.`;

                return;
            }

            this.error = null;
            this.releasePreview();
            this.pendingFile = file;
            this.pendingPreview = kind[0] === 'image' ? URL.createObjectURL(file) : null;
        },

        /**
         * Drop the staged file without sending it.
         */
        removeFile() {
            this.releasePreview();
            this.pendingFile = null;
            this.uploadProgress = 0;
        },

        /**
         * Release the object URL used for the optimistic image preview.
         */
        releasePreview() {
            if (this.pendingPreview !== null) {
                URL.revokeObjectURL(this.pendingPreview);
                this.pendingPreview = null;
            }
        },

        /**
         * Describe a byte count for humans.
         */
        humanSize(bytes) {
            if (bytes < 1024) {
                return `${bytes} B`;
            }

            if (bytes < 1024 * 1024) {
                return `${Math.round(bytes / 1024)} KB`;
            }

            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },

        /**
         * Open an image attachment in the lightbox.
         */
        openLightbox(attachment) {
            this.lightbox = attachment;
            document.getElementById('chat-lightbox')?.showModal();
        },

        /**
         * Close the image lightbox.
         */
        closeLightbox() {
            this.lightbox = null;
            document.getElementById('chat-lightbox')?.close();
        },

        async send() {
            if (! this.canSend) {
                return;
            }

            const body = this.draft.trim();
            const file = this.pendingFile;
            const preview = this.pendingPreview;
            const pendingId = `pending-${Date.now()}`;

            this.draft = '';
            this.uploadProgress = 0;
            this.error = null;
            this.sending = true;
            this.$nextTick(() => this.resize());

            this.messages.push({
                id: pendingId,
                role: 'client',
                type: file === null ? 'text' : this.kindOf(file),
                body,
                time: '',
                pending: true,
                attachment: file === null ? null : {
                    id: pendingId,
                    kind: this.kindOf(file),
                    name: file.name,
                    mime: file.type,
                    size: file.size,
                    size_human: this.humanSize(file.size),
                    url: preview,
                    local: true,
                },
            });

            this.scrollToBottom();

            try {
                const payload = file === null
                    ? await this.postJson({ message: body })
                    : await this.postForm(body, file);

                if (! Array.isArray(payload.messages)) {
                    throw new Error(payload.message ?? 'The message could not be sent.');
                }

                this.removePending(pendingId);
                const messages = reconcileVirtualGreeting(this.messages, payload.messages, this.greeting);
                appendMessages(this.messages, messages);

                if (payload.conversation) {
                    this.mergeConversation(payload.conversation);
                }

                // The file is on the server now, so the staged copy and its
                // object URL are no longer needed.
                this.pendingFile = null;
                this.pendingPreview = null;

                if (preview !== null) {
                    URL.revokeObjectURL(preview);
                }
            } catch (exception) {
                this.removePending(pendingId);
                this.draft = body;
                this.error = exception.message || 'The message could not be sent.';

                // Keep the staged file so the visitor can retry without picking
                // it again. Its preview URL is still live.
            } finally {
                this.sending = false;
                this.uploadProgress = 0;
                this.scrollToBottom();
                this.$nextTick(() => this.resize());
            }
        },

        /**
         * Resolve the kind of a file from its extension.
         */
        kindOf(file) {
            const extension = (file.name.split('.').pop() ?? '').toLowerCase();

            const match = Object.entries(this.uploadKinds)
                .find(([, rules]) => (rules.extensions ?? []).includes(extension));

            return match === undefined ? 'file' : match[0];
        },

        /**
         * Send a text-only message as JSON.
         */
        async postJson(body) {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });

            return response.json().catch(() => ({}));
        },

        /**
         * Send a message with a file, reporting upload progress.
         *
         * `fetch` cannot report upload progress, so this path uses XHR.
         */
        postForm(body, file) {
            return new Promise((resolve, reject) => {
                const form = new FormData();

                if (body !== '') {
                    form.append('message', body);
                }

                form.append('file', file);

                const request = new XMLHttpRequest();

                request.open('POST', this.endpoint);
                request.setRequestHeader('Accept', 'application/json');
                request.setRequestHeader(
                    'X-CSRF-TOKEN',
                    document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                );

                request.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        this.uploadProgress = Math.round((event.loaded / event.total) * 100);
                    }
                };

                request.onload = () => {
                    let payload = {};

                    try {
                        payload = JSON.parse(request.responseText);
                    } catch {
                        payload = {};
                    }

                    if (request.status >= 200 && request.status < 300) {
                        resolve(payload);

                        return;
                    }

                    reject(new Error(this.errorMessage(payload, request.status)));
                };

                request.onerror = () => reject(new Error('The file could not be uploaded.'));
                request.onabort = () => reject(new Error('The upload was cancelled.'));

                request.send(form);
            });
        },

        /**
         * Pick the most useful message out of a failed response.
         */
        errorMessage(payload, status) {
            if (typeof payload.message === 'string' && payload.message !== '') {
                return payload.message;
            }

            const first = Object.values(payload.errors ?? {}).flat()[0];

            if (typeof first === 'string') {
                return first;
            }

            return status === 413
                ? 'That file is too large to upload.'
                : 'The message could not be sent.';
        },

        /**
         * Refresh the transcript so replies from ionvop and assistant state changes appear
         * without a reload.
         *
         * Only messages the browser has not rendered yet are requested, and they are
         * appended to the existing list. Messages already on screen are never replaced,
         * so text selection and scroll position survive every poll.
         */
        async poll() {
            if (! this.polling || this.sending || this.resetting || this.pollInFlight || this.pollEndpoint === '') {
                return;
            }

            const epoch = this.epoch;
            const afterId = lastMessageId(this.messages);
            const url = afterId > 0
                ? `${this.pollEndpoint}?after_id=${afterId}`
                : this.pollEndpoint;

            this.pollInFlight = true;

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                if (! response.ok || epoch !== this.epoch) {
                    return;
                }

                const payload = await response.json().catch(() => ({}));

                if (epoch !== this.epoch) {
                    return;
                }

                if (this.needsFullReload(payload.conversation)) {
                    await this.reloadTranscript(payload.conversation);

                    return;
                }

                const follow = isNearBottom(this.$refs.feed);
                const appended = appendMessages(this.messages, payload.messages ?? []);

                if (payload.conversation) {
                    this.mergeConversation(payload.conversation);
                }

                if (appended > 0 && follow) {
                    this.scrollToBottom();
                }
            } catch {
                // Ignore transient polling failures.
            } finally {
                this.pollInFlight = false;
            }
        },

        /**
         * Detect that the browser is no longer pointed at the rendered conversation,
         * for example after a reset in another tab.
         */
        needsFullReload(incoming) {
            if (! this.conversation?.reference) {
                return false;
            }

            return incoming === null || incoming.reference !== this.conversation.reference;
        },

        /**
         * Replace the transcript with a full copy of a different conversation.
         */
        async reloadTranscript(conversation) {
            try {
                const response = await fetch(this.pollEndpoint, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                if (! response.ok) {
                    return;
                }

                const payload = await response.json().catch(() => ({}));

                replaceAll(this.messages, this.messagesWithGreeting(payload.messages));
                this.conversation = payload.conversation ?? this.emptyShell();
                this.scrollToBottom();
            } catch {
                // Ignore transient polling failures.
            }
        },

        /**
         * Build a fresh copy of the empty conversation shell.
         */
        emptyShell() {
            return { ...(this.emptyConversation ?? {}) };
        },

        /**
         * Keep the greeting in the transcript whenever the conversation is empty.
         */
        messagesWithGreeting(messages) {
            if (Array.isArray(messages) && messages.length > 0) {
                return messages;
            }

            return [this.greetingMessage()];
        },

        /**
         * Build the local greeting entry before a conversation is persisted.
         */
        greetingMessage() {
            return {
                id: 'virtual-greeting',
                renderKey: 'greeting',
                role: 'assistant',
                type: 'text',
                body: this.greeting,
                time: '',
                attachment: null,
                virtualGreeting: true,
            };
        },

        /**
         * Update the conversation in place so unchanged bindings do not re-render.
         */
        mergeConversation(incoming) {
            if (! incoming) {
                return;
            }

            if (! this.conversation) {
                this.conversation = incoming;

                return;
            }

            mergeFields(this.conversation, incoming);
        },

        /**
         * Drop an optimistic message once the server has answered.
         */
        removePending(id) {
            const index = this.messages.findIndex((message) => message.id === id);

            if (index !== -1) {
                this.messages.splice(index, 1);
            }
        },

        /**
         * Open the confirmation dialog before abandoning the conversation.
         */
        confirmReset() {
            document.getElementById('chat-reset-dialog')?.showModal();
        },

        /**
         * Forget the conversation in this browser and start over.
         *
         * The conversation stays in the database for ionvop; only the browser's
         * reference to it is cleared.
         */
        async reset() {
            if (this.resetting || this.sending || this.forgetEndpoint === '') {
                return;
            }

            this.resetting = true;
            this.error = null;

            try {
                const response = await fetch(this.forgetEndpoint, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message ?? 'The conversation could not be reset.');
                }

                this.epoch += 1;
                replaceAll(this.messages, this.messagesWithGreeting(payload.messages));
                this.draft = '';
                this.removeFile();
                this.conversation = payload.conversation ?? this.emptyShell();

                document.getElementById('chat-reset-dialog')?.close();

                const drawer = document.getElementById('chat-info-drawer');

                if (drawer) {
                    drawer.checked = false;
                }

                this.scrollToBottom();
                this.$nextTick(() => this.resize());
            } catch (exception) {
                this.error = exception.message || 'The conversation could not be reset.';
            } finally {
                this.resetting = false;
            }
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const feed = this.$refs.feed;

                if (feed) {
                    feed.scrollTop = feed.scrollHeight;
                }
            });
        },

        resize() {
            const input = this.$refs.input;

            if (! input) {
                return;
            }

            input.style.height = 'auto';
            input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
        },
    };
}
