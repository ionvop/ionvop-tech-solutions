const POLL_MS = 10_000;

export default function chat(initial) {
    return {
        messages: initial.messages ?? [],
        conversation: initial.conversation ?? null,
        stages: initial.stages ?? [],
        greeting: initial.greeting ?? '',
        endpoint: initial.endpoint ?? '',
        pollEndpoint: initial.pollEndpoint ?? '',

        draft: '',
        sending: false,
        error: null,
        polling: true,
        timers: [],

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
            return this.draft.trim().length > 0 && ! this.sending;
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

                this.messages = this.messages.filter((message) => message.id !== pendingId);
                this.messages.push(...(payload.messages ?? []));

                if (payload.conversation) {
                    this.conversation = payload.conversation;
                }

                if (payload.error) {
                    this.error = payload.error;
                }
            } catch (exception) {
                this.messages = this.messages.filter((message) => message.id !== pendingId);
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
         */
        async poll() {
            if (! this.polling || this.sending || this.pollEndpoint === '') {
                return;
            }

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
                const incoming = payload.messages ?? [];
                const grew = incoming.length > this.messages.length;

                this.messages = incoming;

                if (payload.conversation) {
                    this.conversation = payload.conversation;
                }

                if (grew) {
                    this.scrollToBottom();
                }
            } catch {
                // Ignore transient polling failures.
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
