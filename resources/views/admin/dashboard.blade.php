@extends('layouts.app')

@section('title', 'Admin — ionvop')
@section('description', 'Review client inquiries, reply to conversations, and manage project details.')

@section('fullscreen', 'true')

@section('content')
    <div
        class="flex min-h-0 flex-1 flex-col overflow-hidden"
        x-data="admin({
            conversations: @js($conversations),
            selected: @js($selected),
            messages: @js($messages),
            stages: @js($stages),
            contractStatuses: @js($contractStatuses),
            currencies: @js($currencies),
            user: @js(['email' => $user->email]),
            uploadKinds: @js(config('chat.uploads.kinds')),
            endpoints: {
                list: @js(route('admin.conversations.index')),
                show: @js(route('admin.conversations.show', ['conversation' => '__REFERENCE__'])),
                update: @js(route('admin.conversations.update', ['conversation' => '__REFERENCE__'])),
                destroy: @js(route('admin.conversations.destroy', ['conversation' => '__REFERENCE__'])),
                messages: @js(route('admin.conversations.messages.store', ['conversation' => '__REFERENCE__'])),
                summary: @js(route('admin.conversations.summary', ['conversation' => '__REFERENCE__'])),
            },
        })"
    >
        <header class="flex-none border-b border-base-300 bg-base-100">
            <div class="flex items-center gap-3 px-3 py-2.5 sm:px-5">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid size-8 -skew-x-6 place-items-center rounded-field bg-primary text-primary-content">
                        <span class="skew-x-6 text-base font-bold leading-none">i</span>
                    </span>
                    <span class="hidden text-sm font-bold tracking-tight sm:inline">ionvop</span>
                </a>

                <span class="badge badge-soft badge-secondary badge-sm">Admin</span>

                <div class="ml-auto flex items-center gap-2">
                    <span class="hidden items-center gap-2 text-xs text-base-content/50 md:flex">
                        <span class="status status-success status-xs"></span>
                        <span x-text="polling ? 'Live' : 'Paused'"></span>
                    </span>

                    <span class="hidden max-w-48 truncate text-xs text-base-content/60 lg:inline" x-text="user.email"></span>

                    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm">View site</a>

                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="flex-none border-b border-base-300 bg-base-100 px-3 py-2 lg:hidden">
            <div role="tablist" class="tabs tabs-box tabs-sm">
                <button
                    type="button"
                    role="tab"
                    class="tab"
                    :class="pane === 'list' ? 'tab-active' : ''"
                    @click="pane = 'list'"
                >
                    Conversations
                    <span class="badge badge-xs badge-primary ml-1" x-show="unreadCount > 0" x-text="unreadCount" x-cloak></span>
                </button>
                <button type="button" role="tab" class="tab" :class="pane === 'chat' ? 'tab-active' : ''" @click="pane = 'chat'">
                    Chat
                </button>
                <button type="button" role="tab" class="tab" :class="pane === 'details' ? 'tab-active' : ''" @click="pane = 'details'">
                    Details
                </button>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[19rem_minmax(0,1fr)_21rem]">
            {{-- Conversation list --}}
            <aside
                class="flex min-h-0 flex-col border-base-300 bg-base-200 lg:border-r"
                :class="pane === 'list' ? 'flex' : 'hidden lg:flex'"
            >
                <div class="flex-none space-y-2 border-b border-base-300 p-3">
                    <label class="input input-sm w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-4 opacity-50" aria-hidden="true">
                            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
                        </svg>
                        <input
                            type="search"
                            placeholder="Search name, email, reference…"
                            x-model="query"
                            @input.debounce.300ms="refreshList()"
                            aria-label="Search conversations"
                        >
                    </label>

                    <select
                        class="select select-sm w-full"
                        x-model="stageFilter"
                        @change="refreshList()"
                        aria-label="Filter by stage"
                    >
                        <option value="">All stages</option>
                        <template x-for="(label, value) in stages" :key="value">
                            <option :value="value" x-text="label"></option>
                        </template>
                    </select>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
                    <template x-for="conversation in conversations" :key="conversation.reference">
                        <button
                            type="button"
                            class="mb-1 w-full rounded-box border p-3 text-left transition"
                            :class="selected?.reference === conversation.reference
                                ? 'border-primary/60 bg-base-100'
                                : 'border-transparent hover:bg-base-100/60'"
                            @click="select(conversation.reference)"
                        >
                            <div class="flex items-start gap-2">
                                <span
                                    class="mt-1.5 size-2 shrink-0 rounded-full"
                                    :class="conversation.unread ? 'bg-primary' : 'bg-transparent'"
                                ></span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2">
                                        <p class="truncate text-sm font-semibold" x-text="conversation.label"></p>
                                        <span
                                            class="ml-auto shrink-0 text-[0.65rem] text-base-content/40"
                                            x-text="conversation.last_message_human ?? ''"
                                        ></span>
                                    </div>

                                    <p class="truncate text-xs text-base-content/50" x-text="conversation.client_name ?? 'No name yet'"></p>

                                    <p
                                        class="mt-1 line-clamp-2 text-xs leading-relaxed text-base-content/40"
                                        x-text="conversation.preview ?? 'No messages yet'"
                                    ></p>

                                    <div class="mt-2 flex flex-wrap items-center gap-1">
                                        <span class="badge badge-ghost badge-xs" x-text="conversation.stage_label"></span>
                                        <span
                                            class="badge badge-warning badge-xs"
                                            x-show="! conversation.ai_enabled"
                                            x-cloak
                                        >AI paused</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </template>

                    <div class="px-3 py-10 text-center" x-show="conversations.length === 0" x-cloak>
                        <p class="text-sm text-base-content/50">No conversations match.</p>
                    </div>
                </div>
            </aside>

            {{-- Transcript --}}
            <section
                class="flex min-h-0 flex-col bg-base-100"
                :class="pane === 'chat' ? 'flex' : 'hidden lg:flex'"
            >
                <template x-if="selected">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="flex-none border-b border-base-300 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold" x-text="selected.label"></p>
                                    <p class="truncate text-xs text-base-content/50">
                                        <span class="font-mono" x-text="`#${selected.reference}`"></span>
                                        <span x-show="selected.client_email" x-cloak>
                                            · <span x-text="selected.client_email"></span>
                                        </span>
                                    </p>
                                </div>

                                <span class="badge badge-ghost badge-sm" x-text="selected.stage_label"></span>
                            </div>
                        </div>

                        <div
                            x-ref="feed"
                            class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4"
                            role="log"
                            aria-live="polite"
                            aria-label="Conversation transcript"
                        >
                            <template x-for="message in messages" :key="message.id">
                                <div
                                    class="chat"
                                    :class="message.role === 'client' ? 'chat-start' : 'chat-end'"
                                >
                                    <div class="chat-image avatar avatar-placeholder">
                                        <div
                                            class="w-8 rounded-full text-xs font-semibold"
                                            :class="{
                                                'bg-base-300 text-base-content/70': message.role === 'client',
                                                'bg-primary text-primary-content': message.role === 'assistant',
                                                'bg-secondary text-secondary-content': message.role === 'admin',
                                            }"
                                        >
                                            <span x-text="message.role === 'client'
                                                ? (selected.client_name?.charAt(0).toUpperCase() ?? 'C')
                                                : (message.role === 'admin' ? 'i' : 'A')"></span>
                                        </div>
                                    </div>

                                    <div class="chat-header">
                                        <span x-text="message.role === 'client'
                                            ? (selected.client_name ?? 'Client')
                                            : (message.role === 'admin' ? 'ionvop' : 'Assistant')"></span>
                                        <time class="opacity-50" x-text="message.time"></time>
                                    </div>

                                    <div
                                        class="chat-bubble whitespace-pre-line leading-relaxed"
                                        :class="{
                                            'chat-bubble-primary': message.role === 'client',
                                            'chat-bubble-secondary': message.role === 'admin',
                                        }"
                                    >
                                        <template x-if="message.attachment">
                                            <div class="mb-1">
                                                <template x-if="message.attachment.kind === 'image'">
                                                    <button
                                                        type="button"
                                                        class="block overflow-hidden rounded-field"
                                                        @click="openLightbox(message.attachment)"
                                                        :aria-label="`Open ${message.attachment.name}`"
                                                    >
                                                        <img
                                                            :src="message.attachment.url"
                                                            :alt="message.attachment.name"
                                                            class="max-h-64 w-auto max-w-full object-cover"
                                                            loading="lazy"
                                                        >
                                                    </button>
                                                </template>

                                                <template x-if="message.attachment.kind === 'audio'">
                                                    <audio
                                                        :src="message.attachment.url"
                                                        class="w-64 max-w-full"
                                                        controls
                                                        preload="metadata"
                                                    ></audio>
                                                </template>

                                                <template x-if="message.attachment.kind === 'video'">
                                                    <video
                                                        :src="message.attachment.url"
                                                        class="max-h-64 w-auto max-w-full rounded-field"
                                                        controls
                                                        preload="metadata"
                                                        playsinline
                                                    ></video>
                                                </template>

                                                <template x-if="message.attachment.kind === 'file'">
                                                    <a
                                                        :href="message.attachment.url"
                                                        class="link link-hover flex items-center gap-2 text-sm"
                                                        :download="message.attachment.name"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5 shrink-0" aria-hidden="true">
                                                            <path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z" />
                                                        </svg>
                                                        <span class="truncate" x-text="message.attachment.name"></span>
                                                    </a>
                                                </template>

                                                <p
                                                    class="mt-1 text-[0.65rem] opacity-60"
                                                    x-text="`${message.attachment.name} · ${message.attachment.size_human}`"
                                                ></p>
                                            </div>
                                        </template>

                                        <span x-show="message.body" x-text="message.body"></span>
                                    </div>

                                    <div class="chat-footer" x-show="message.reason" x-cloak>
                                        <span class="text-[0.65rem] text-base-content/40" x-text="`Paused: ${message.reason}`"></span>
                                    </div>
                                </div>
                            </template>

                            <div class="chat chat-end" x-show="sending" x-cloak>
                                <div class="chat-bubble flex items-center gap-1">
                                    <span class="loading loading-dots loading-sm"></span>
                                    <span class="text-xs opacity-70">sending</span>
                                </div>
                            </div>

                            <p class="py-10 text-center text-sm text-base-content/40" x-show="messages.length === 0" x-cloak>
                                No messages in this conversation yet.
                            </p>
                        </div>

                        <div class="flex-none border-t border-base-300 px-4 py-3">
                            <div class="alert alert-error mb-2 text-sm" x-show="error" x-cloak>
                                <span x-text="error"></span>
                            </div>

                            <p
                                class="mb-2 text-center text-xs text-base-content/40"
                                x-show="! selected.ai_enabled"
                                x-cloak
                            >
                                The assistant is paused — replies come from you only.
                            </p>

                            <form class="rounded-box border border-base-300 bg-base-200/60 p-2" @submit.prevent="send()">
                                <label for="admin-reply" class="sr-only">Reply as ionvop</label>
                                <textarea
                                    id="admin-reply"
                                    x-ref="input"
                                    x-model="draft"
                                    rows="1"
                                    class="textarea textarea-ghost min-h-11 w-full resize-none px-2 py-1.5 text-sm leading-relaxed"
                                    placeholder="Reply as ionvop…"
                                    maxlength="2000"
                                    @input="resize()"
                                    @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send(); }"
                                ></textarea>

                                <div class="mt-1 flex items-center gap-2">
                                    <input
                                        x-ref="fileInput"
                                        type="file"
                                        class="hidden"
                                        :accept="accept"
                                        @change="onFileSelected($event)"
                                    >

                                    <div class="tooltip tooltip-top" data-tip="Attach a file">
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-square btn-sm"
                                            aria-label="Attach a file"
                                            :disabled="sending"
                                            @click="pickFile()"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                                <path d="M720-330q0 104-73 177T470-80q-104 0-177-73t-73-177v-370q0-75 52.5-127.5T400-880q75 0 127.5 52.5T580-700v350q0 46-32 78t-78 32q-46 0-78-32t-32-78v-370h80v370q0 13 8.5 21.5T470-320q13 0 21.5-8.5T500-350v-350q0-42-29-71t-71-29q-42 0-71 29t-29 71v370q0 71 49.5 120.5T470-160q71 0 120.5-49.5T640-330v-390h80v390Z" />
                                            </svg>
                                        </button>
                                    </div>

                                    <span class="text-xs text-base-content/40">Enter to send</span>

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-sm ml-auto gap-1.5"
                                        :disabled="! canSend"
                                    >
                                        <span class="loading loading-spinner loading-xs" x-show="sending" x-cloak></span>
                                        Send
                                    </button>
                                </div>

                                <div class="mt-1" x-show="pendingFile" x-cloak>
                                    <div class="flex items-center gap-2 rounded-field bg-base-100 px-2 py-1.5">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-medium" x-text="pendingFile?.name"></p>
                                            <p class="text-[0.65rem] text-base-content/50" x-text="humanSize(pendingFile?.size ?? 0)"></p>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-square btn-xs"
                                            aria-label="Remove attachment"
                                            :disabled="sending"
                                            @click="removeFile()"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-4" aria-hidden="true">
                                                <path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360Z" />
                                            </svg>
                                        </button>
                                    </div>

                                    <progress
                                        class="progress progress-primary mt-1 h-1 w-full"
                                        :value="uploadProgress"
                                        max="100"
                                        x-show="sending && uploadProgress > 0"
                                        x-cloak
                                    ></progress>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>

                <div class="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center" x-show="! selected" x-cloak>
                    <p class="text-sm font-medium">No conversation selected</p>
                    <p class="max-w-xs text-xs leading-relaxed text-base-content/50">
                        Pick a conversation from the list to read the transcript and reply.
                    </p>
                </div>
            </section>

            {{-- Details --}}
            <aside
                class="min-h-0 overflow-y-auto overscroll-contain border-base-300 bg-base-200 lg:border-l"
                :class="pane === 'details' ? 'block' : 'hidden lg:block'"
            >
                <template x-if="selected">
                    <div class="space-y-4 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold">Project details</h2>
                            <span class="badge badge-ghost badge-sm" x-text="selected.stage_label"></span>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveDetails()">
                            <fieldset class="fieldset gap-1.5">
                                <legend class="fieldset-legend text-xs">Conversation label</legend>
                                <input type="text" class="input input-sm w-full" x-model="form.label" maxlength="255">
                            </fieldset>

                            <div class="grid grid-cols-2 gap-2">
                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Client name</legend>
                                    <input type="text" class="input input-sm w-full" x-model="form.client_name" maxlength="255">
                                </fieldset>

                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Client email</legend>
                                    <input type="email" class="input input-sm w-full" x-model="form.client_email" maxlength="255">
                                </fieldset>
                            </div>

                            <fieldset class="fieldset gap-1.5">
                                <legend class="fieldset-legend text-xs">Project stage</legend>
                                <select class="select select-sm w-full" x-model="form.stage">
                                    <template x-for="(label, value) in stages" :key="value">
                                        <option :value="value" x-text="label"></option>
                                    </template>
                                </select>
                            </fieldset>

                            <div class="divider my-1 text-xs text-base-content/40">Payment</div>

                            <div class="grid grid-cols-2 gap-2">
                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Currency</legend>
                                    <select class="select select-sm w-full" x-model="form.currency">
                                        <template x-for="currency in currencies" :key="currency">
                                            <option :value="currency" x-text="currency"></option>
                                        </template>
                                    </select>
                                </fieldset>

                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">First month</legend>
                                    <input type="number" min="0" class="input input-sm w-full" x-model.number="form.first_month_amount">
                                </fieldset>

                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Monthly</legend>
                                    <input type="number" min="0" class="input input-sm w-full" x-model.number="form.monthly_amount">
                                </fieldset>

                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Paid</legend>
                                    <input type="number" min="0" class="input input-sm w-full" x-model.number="form.amount_paid">
                                </fieldset>

                                <fieldset class="fieldset gap-1.5 col-span-2">
                                    <legend class="fieldset-legend text-xs">Due</legend>
                                    <input type="number" min="0" class="input input-sm w-full" x-model.number="form.amount_due">
                                </fieldset>
                            </div>

                            <div class="divider my-1 text-xs text-base-content/40">Contract</div>

                            <fieldset class="fieldset gap-1.5">
                                <legend class="fieldset-legend text-xs">Status</legend>
                                <select class="select select-sm w-full" x-model="form.contract_status">
                                    <option value="">No contract</option>
                                    <template x-for="status in contractStatuses" :key="status">
                                        <option :value="status" x-text="status"></option>
                                    </template>
                                </select>
                            </fieldset>

                            <div class="grid grid-cols-2 gap-2">
                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Started</legend>
                                    <input type="date" class="input input-sm w-full" x-model="form.contract_started_at">
                                </fieldset>

                                <fieldset class="fieldset gap-1.5">
                                    <legend class="fieldset-legend text-xs">Renews</legend>
                                    <input type="date" class="input input-sm w-full" x-model="form.contract_renews_at">
                                </fieldset>
                            </div>

                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-box bg-base-100 p-3">
                                <span class="text-xs">
                                    <span class="block font-medium">AI assistant</span>
                                    <span class="block text-base-content/50">Let the assistant reply to this client.</span>
                                </span>
                                <input type="checkbox" class="toggle toggle-sm toggle-primary" x-model="form.ai_enabled">
                            </label>

                            <button type="submit" class="btn btn-primary btn-sm w-full" :disabled="saving">
                                <span class="loading loading-spinner loading-xs" x-show="saving" x-cloak></span>
                                <span x-text="saving ? 'Saving…' : 'Save details'"></span>
                            </button>
                        </form>

                        <div class="divider my-1 text-xs text-base-content/40">AI summary</div>

                        <div class="rounded-box bg-base-100 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Brief</p>
                                <span
                                    class="text-[0.65rem] text-base-content/40"
                                    x-show="selected.summary_generated_human"
                                    x-text="`Generated ${selected.summary_generated_human}`"
                                    x-cloak
                                ></span>
                            </div>

                            <p
                                class="mt-2 whitespace-pre-line text-xs leading-relaxed text-base-content/70"
                                x-show="selected.summary"
                                x-text="selected.summary"
                                x-cloak
                            ></p>

                            <p class="mt-2 text-xs leading-relaxed text-base-content/40" x-show="! selected.summary" x-cloak>
                                No summary yet. Generate one to get a short brief of the inquiry.
                            </p>

                            <button
                                type="button"
                                class="btn btn-ghost btn-xs mt-3 w-full"
                                :disabled="summarizing"
                                @click="generateSummary()"
                            >
                                <span class="loading loading-spinner loading-xs" x-show="summarizing" x-cloak></span>
                                <span x-text="summarizing ? 'Generating…' : (selected.summary ? 'Regenerate summary' : 'Generate summary')"></span>
                            </button>
                        </div>

                        <div class="divider my-1 text-xs text-base-content/40">Danger zone</div>

                        <button type="button" class="btn btn-error btn-outline btn-sm w-full" @click="confirmDelete()">
                            Delete conversation
                        </button>

                        <p class="text-[0.65rem] leading-relaxed text-base-content/40">
                            Started <span x-text="selected.created_human"></span>.
                            Deleting removes the conversation and every message in it.
                        </p>
                    </div>
                </template>

                <div class="p-6 text-center" x-show="! selected" x-cloak>
                    <p class="text-xs text-base-content/50">Select a conversation to edit its details.</p>
                </div>
            </aside>
        </div>

        <dialog id="admin-delete-dialog" class="modal">
            <div class="modal-box">
                <h3 class="text-lg font-bold">Delete this conversation?</h3>
                <p class="py-4 text-sm leading-relaxed text-base-content/70">
                    <span x-text="selected?.label ?? 'This conversation'"></span> and all of its messages will be
                    permanently removed. This cannot be undone.
                </p>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost btn-sm">Cancel</button>
                    </form>

                    <button type="button" class="btn btn-error btn-sm" :disabled="deleting" @click="destroy()">
                        <span class="loading loading-spinner loading-xs" x-show="deleting" x-cloak></span>
                        Delete
                    </button>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <dialog id="admin-lightbox" class="modal">
            <div class="modal-box max-w-4xl p-2">
                <template x-if="lightbox">
                    <figure class="flex flex-col gap-2">
                        <img
                            :src="lightbox.url"
                            :alt="lightbox.name"
                            class="max-h-[75vh] w-full rounded-box object-contain"
                        >
                        <figcaption class="flex items-center justify-between gap-2 px-1 pb-1">
                            <span class="truncate text-xs text-base-content/60" x-text="lightbox.name"></span>
                            <a
                                :href="lightbox.url"
                                :download="lightbox.name"
                                class="btn btn-ghost btn-xs"
                            >Download</a>
                        </figcaption>
                    </figure>
                </template>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <div class="toast toast-end toast-bottom z-50">
            <div class="alert alert-success text-sm" x-show="toast" x-cloak x-transition.opacity>
                <span x-text="toast"></span>
            </div>
        </div>
    </div>
@endsection
