import { appendMessages, isNearBottom, lastMessageId, mergeFields, replaceAll } from './transcript';

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

        draft: '',
        sending: false,
        resetting: false,
        error: null,
        polling: true,
        pollInFlight: false,
        timers: [],
        epoch: 0,

        init() {
            this.scrollToBottom();

            this.timers.push(setInterval(() => this.poll(), POLL_MS));

            document.addEventListener('visibilitychange', () => {
                this.polling = ! document.hidden;
            });
        },

        destroy() {
            this.timers.forEach((timer) => clearInterval(timer));
            this.timers = [];
        },

        get canSend() {
            return this.draft.trim().length > 0 && ! this.sending && ! this.resetting;
        },

        get hasMessages() {
            return this.messages.length > 0;
        },

        async send() {
            if (! this.canSend) {
                return;
            }

            const body = this.draft.trim();
            const pendingId = `pending-${Date.now()}`;

            this.draft = '';
            this.error = null;
            this.sending = true;
            this.$nextTick(() => this.resize());

            this.messages.push({
                id: pendingId,
                role: 'client',
                type: 'text',
                body,
                time: '',
                pending: true,
            });

            this.scrollToBottom();

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ message: body }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok && ! Array.isArray(payload.messages)) {
                    throw new Error(payload.message ?? 'The message could not be sent.');
                }

                this.removePending(pendingId);
                appendMessages(this.messages, payload.messages ?? []);

                if (payload.conversation) {
                    this.mergeConversation(payload.conversation);
                }
            } catch (exception) {
                this.removePending(pendingId);
                this.draft = body;
                this.error = exception.message || 'The message could not be sent.';
            } finally {
                this.sending = false;
                this.scrollToBottom();
                this.$nextTick(() => this.resize());
            }
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

                replaceAll(this.messages, payload.messages ?? []);
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
                replaceAll(this.messages, payload.messages ?? []);
                this.draft = '';
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
