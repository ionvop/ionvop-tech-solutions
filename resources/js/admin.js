import { appendMessages, isNearBottom, lastMessageId, mergeFields, mergeList, replaceAll } from './transcript';

const POLL_CONVERSATION_MS = 10_000;
const POLL_LIST_MS = 30_000;

export default function admin(initial) {
    return {
        conversations: initial.conversations ?? [],
        selected: initial.selected ?? null,
        messages: initial.messages ?? [],
        stages: initial.stages ?? {},
        contractStatuses: initial.contractStatuses ?? [],
        currencies: initial.currencies ?? [],
        user: initial.user ?? {},
        endpoints: initial.endpoints ?? {},

        pane: 'chat',
        query: '',
        stageFilter: '',
        draft: '',
        form: {},
        sending: false,
        saving: false,
        summarizing: false,
        deleting: false,
        polling: true,
        pollInFlight: false,
        error: null,
        toast: null,

        timers: [],
        toastTimer: null,

        init() {
            this.pane = window.matchMedia('(min-width: 1024px)').matches ? 'chat' : 'list';

            this.syncForm();
            this.scrollToBottom();

            this.timers.push(setInterval(() => this.pollConversation(), POLL_CONVERSATION_MS));
            this.timers.push(setInterval(() => this.refreshList(), POLL_LIST_MS));

            document.addEventListener('visibilitychange', () => {
                this.polling = ! document.hidden;
            });
        },

        get canSend() {
            return this.draft.trim().length > 0 && ! this.sending && this.selected !== null;
        },

        get unreadCount() {
            return this.conversations.filter((conversation) => conversation.unread).length;
        },

        /**
         * Build an endpoint URL for a conversation reference.
         */
        url(key, reference) {
            return (this.endpoints[key] ?? '').replace('__REFERENCE__', encodeURIComponent(reference));
        },

        /**
         * Copy the selected conversation into the editable form.
         */
        syncForm() {
            const conversation = this.selected;

            if (! conversation) {
                this.form = {};

                return;
            }

            this.form = {
                label: conversation.label ?? '',
                client_name: conversation.client_name ?? '',
                client_email: conversation.client_email ?? '',
                stage: conversation.stage ?? '',
                currency: conversation.currency ?? 'PHP',
                first_month_amount: conversation.first_month_amount ?? null,
                monthly_amount: conversation.monthly_amount ?? null,
                amount_paid: conversation.amount_paid ?? null,
                amount_due: conversation.amount_due ?? null,
                contract_status: conversation.contract_status ?? '',
                contract_started_at: conversation.contract_started_at ?? '',
                contract_renews_at: conversation.contract_renews_at ?? '',
                ai_enabled: Boolean(conversation.ai_enabled),
            };
        },

        /**
         * Open a conversation and load its transcript.
         */
        async select(reference) {
            if (this.selected?.reference === reference) {
                this.pane = 'chat';

                return;
            }

            this.error = null;

            try {
                const payload = await this.request(this.url('show', reference));

                this.selected = payload.conversation;
                replaceAll(this.messages, payload.messages ?? []);
                this.syncForm();
                this.pane = 'chat';

                this.markRead(reference);
                this.scrollToBottom();
            } catch (exception) {
                this.error = exception.message;
            }
        },

        /**
         * Reload the conversation list, honouring the current filters.
         */
        async refreshList() {
            const params = new URLSearchParams();

            if (this.query.trim() !== '') {
                params.set('q', this.query.trim());
            }

            if (this.stageFilter !== '') {
                params.set('stage', this.stageFilter);
            }

            const suffix = params.toString() === '' ? '' : `?${params.toString()}`;

            try {
                const payload = await this.request(`${this.endpoints.list}${suffix}`);

                mergeList(this.conversations, payload.conversations ?? [], (conversation) => conversation.reference);
            } catch {
                // A failed background refresh should not interrupt the admin.
            }
        },

        /**
         * Poll the open conversation for new messages.
         *
         * Only messages the dashboard has not rendered yet are requested, and they
         * are appended to the existing list. Messages already on screen are never
         * replaced, so text selection and scroll position survive every poll.
         */
        async pollConversation() {
            if (! this.polling || this.selected === null || this.sending || this.pollInFlight) {
                return;
            }

            const reference = this.selected.reference;
            const afterId = lastMessageId(this.messages);
            const url = afterId > 0
                ? `${this.url('show', reference)}?after_id=${afterId}`
                : this.url('show', reference);

            this.pollInFlight = true;

            try {
                const payload = await this.request(url);

                // The admin may have switched conversations while the request was in flight.
                if (this.selected?.reference !== reference) {
                    return;
                }

                const follow = isNearBottom(this.$refs.feed);
                const appended = appendMessages(this.messages, payload.messages ?? []);

                if (payload.conversation) {
                    this.mergeSelected(payload.conversation);
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
         * Update the selected conversation in place so unchanged bindings do not re-render.
         */
        mergeSelected(incoming) {
            if (! this.selected) {
                this.selected = incoming;

                return;
            }

            mergeFields(this.selected, incoming);
        },

        /**
         * Post a reply from ionvop.
         */
        async send() {
            if (! this.canSend) {
                return;
            }

            const body = this.draft.trim();
            const reference = this.selected.reference;

            this.draft = '';
            this.error = null;
            this.sending = true;
            this.$nextTick(() => this.resize());

            try {
                const payload = await this.request(this.url('messages', reference), {
                    method: 'POST',
                    body: { message: body },
                });

                appendMessages(this.messages, [payload.message]);
                this.mergeSelected(payload.conversation);
                this.syncForm();
                this.scrollToBottom();
                this.refreshList();
            } catch (exception) {
                this.draft = body;
                this.error = exception.message;
            } finally {
                this.sending = false;
                this.$nextTick(() => this.resize());
            }
        },

        /**
         * Persist the editable project details.
         */
        async saveDetails() {
            if (this.selected === null || this.saving) {
                return;
            }

            this.saving = true;
            this.error = null;

            try {
                const payload = await this.request(this.url('update', this.selected.reference), {
                    method: 'PATCH',
                    body: this.formPayload(),
                });

                this.mergeSelected(payload.conversation);
                this.syncForm();
                this.refreshList();
                this.notify('Details saved.');
            } catch (exception) {
                this.error = exception.message;
            } finally {
                this.saving = false;
            }
        },

        /**
         * Ask the assistant for a fresh administrative summary.
         */
        async generateSummary() {
            if (this.selected === null || this.summarizing) {
                return;
            }

            this.summarizing = true;
            this.error = null;

            try {
                const payload = await this.request(this.url('summary', this.selected.reference), {
                    method: 'POST',
                });

                this.mergeSelected(payload.conversation);
                this.notify('Summary generated.');
            } catch (exception) {
                this.error = exception.message;
            } finally {
                this.summarizing = false;
            }
        },

        /**
         * Open the delete confirmation dialog.
         */
        confirmDelete() {
            document.getElementById('admin-delete-dialog')?.showModal();
        },

        /**
         * Delete the selected conversation.
         */
        async destroy() {
            if (this.selected === null || this.deleting) {
                return;
            }

            const reference = this.selected.reference;

            this.deleting = true;
            this.error = null;

            try {
                await this.request(this.url('destroy', reference), { method: 'DELETE' });

                document.getElementById('admin-delete-dialog')?.close();

                const index = this.conversations.findIndex(
                    (conversation) => conversation.reference === reference,
                );

                if (index !== -1) {
                    this.conversations.splice(index, 1);
                }

                this.selected = null;
                replaceAll(this.messages, []);
                this.syncForm();
                this.notify('Conversation deleted.');
            } catch (exception) {
                this.error = exception.message;
            } finally {
                this.deleting = false;
            }
        },

        /**
         * Normalise the form into the shape the update endpoint expects.
         */
        formPayload() {
            const amount = (value) => (value === null || value === '' ? null : Number(value));

            return {
                label: this.form.label || null,
                client_name: this.form.client_name || null,
                client_email: this.form.client_email || null,
                stage: this.form.stage,
                currency: this.form.currency,
                first_month_amount: amount(this.form.first_month_amount),
                monthly_amount: amount(this.form.monthly_amount),
                amount_paid: amount(this.form.amount_paid),
                amount_due: amount(this.form.amount_due),
                contract_status: this.form.contract_status || null,
                contract_started_at: this.form.contract_started_at || null,
                contract_renews_at: this.form.contract_renews_at || null,
                ai_enabled: Boolean(this.form.ai_enabled),
            };
        },

        /**
         * Clear the unread marker for a conversation in the local list.
         */
        markRead(reference) {
            const conversation = this.conversations.find((item) => item.reference === reference);

            if (conversation) {
                conversation.unread = false;
            }
        },

        /**
         * Show a short success message.
         */
        notify(message) {
            this.toast = message;

            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toast = null;
            }, 2500);
        },

        /**
         * Send a JSON request and unwrap the response.
         */
        async request(url, options = {}) {
            const response = await fetch(url, {
                method: options.method ?? 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: options.body === undefined ? undefined : JSON.stringify(options.body),
            });

            const payload = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(this.errorMessage(payload, response.status));
            }

            return payload;
        },

        /**
         * Pick the most useful message out of a failed response.
         */
        errorMessage(payload, status) {
            if (typeof payload.message === 'string' && payload.message !== '') {
                return payload.message;
            }

            const errors = payload.errors ?? {};
            const first = Object.values(errors).flat()[0];

            if (typeof first === 'string') {
                return first;
            }

            return status === 403
                ? 'You are not allowed to do that.'
                : 'Something went wrong. Please try again.';
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
