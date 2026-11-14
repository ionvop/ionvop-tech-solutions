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
        uploadKinds: initial.uploadKinds ?? {},

        pane: 'chat',
        query: '',
        stageFilter: '',
        draft: '',
        pendingFile: null,
        uploadProgress: 0,
        lightbox: null,
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
            return (this.draft.trim().length > 0 || this.pendingFile !== null)
                && ! this.sending
                && this.selected !== null;
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
         */
        pickFile() {
            if (this.sending || this.selected === null) {
                return;
            }

            this.$refs.fileInput?.click();
        },

        /**
         * Validate and stage a file ionvop picked.
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
            this.pendingFile = file;
        },

        /**
         * Drop the staged file without sending it.
         */
        removeFile() {
            this.pendingFile = null;
            this.uploadProgress = 0;
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
            document.getElementById('admin-lightbox')?.showModal();
        },

        /**
         * Close the image lightbox.
         */
        closeLightbox() {
            this.lightbox = null;
            document.getElementById('admin-lightbox')?.close();
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

                // A file staged for the previous conversation must not follow
                // the admin into this one.
                this.removeFile();
                this.draft = '';

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
         *
         * A reply may carry one uploaded file, which is sent as multipart form
         * data so the upload can report progress.
         */
        async send() {
            if (! this.canSend) {
                return;
            }

            const body = this.draft.trim();
            const file = this.pendingFile;
            const reference = this.selected.reference;

            this.draft = '';
            this.uploadProgress = 0;
            this.error = null;
            this.sending = true;
            this.$nextTick(() => this.resize());

            try {
                const payload = file === null
                    ? await this.request(this.url('messages', reference), {
                        method: 'POST',
                        body: { message: body },
                    })
                    : await this.upload(this.url('messages', reference), body, file);

                appendMessages(this.messages, [payload.message]);
                this.mergeSelected(payload.conversation);
                this.syncForm();
                this.scrollToBottom();
                this.refreshList();

                // The file is on the server now, so the staged copy is no longer needed.
                this.pendingFile = null;
            } catch (exception) {
                this.draft = body;
                this.pendingFile = file;
                this.error = exception.message;
            } finally {
                this.sending = false;
                this.uploadProgress = 0;
                this.$nextTick(() => this.resize());
            }
        },

        /**
         * Upload a reply with a file, reporting progress.
         *
         * `fetch` cannot report upload progress, so this path uses XHR.
         */
        upload(url, body, file) {
            return new Promise((resolve, reject) => {
                const form = new FormData();

                if (body !== '') {
                    form.append('message', body);
                }

                form.append('file', file);

                const request = new XMLHttpRequest();

                request.open('POST', url);
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
