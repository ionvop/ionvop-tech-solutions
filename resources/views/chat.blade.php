@extends('layouts.app')

@section('title', 'Chat — ionvop')
@section('description', 'Discuss your business problem, share requirements, and follow your project status.')

@section('fullscreen', 'true')

@section('content')
    <div
        class="drawer min-h-0 flex-1 grid-rows-[minmax(0,1fr)] overflow-hidden lg:drawer-open"
        x-data="chat({
            messages: @js($messages),
            conversation: @js($conversation),
            emptyConversation: @js($emptyConversation),
            stages: @js(\App\Models\Conversation::STAGES),
            greeting: @js(\App\Models\Conversation::GREETING),
            endpoint: @js(route('chat.messages.store')),
            pollEndpoint: @js(route('chat.messages.index')),
            forgetEndpoint: @js(route('chat.conversation.forget')),
            uploadKinds: @js(config('chat.uploads.kinds')),
        })"
    >
        <input id="chat-info-drawer" type="checkbox" class="drawer-toggle">

        <div class="drawer-content flex min-h-0 flex-col overflow-hidden">
            <header class="flex-none border-b border-base-300 bg-base-100">
                <div class="mx-auto flex w-full max-w-3xl items-center gap-3 px-3 py-2.5 sm:px-6">
                    <label
                        for="chat-info-drawer"
                        class="btn btn-ghost btn-square btn-sm lg:hidden"
                        aria-label="Toggle project details"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                            <path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z" />
                        </svg>
                    </label>

                    <div class="avatar avatar-placeholder">
                        <div class="w-9 rounded-full bg-primary text-sm font-bold text-primary-content">
                            <span>i</span>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold" x-text="conversation.label">Project conversation</p>
                        <p class="flex items-center gap-1.5 text-xs text-base-content/60">
                            <span class="status status-success status-xs"></span>
                            <span x-text="conversation.ai_enabled ? 'Replies within a day' : 'ionvop is handling this conversation'"></span>
                        </p>
                    </div>

                    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm">Back to site</a>
                </div>
            </header>

            <div
                x-ref="feed"
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                role="log"
                aria-live="polite"
                aria-label="Conversation"
            >
                <div class="mx-auto flex w-full max-w-3xl flex-col px-3 py-4 sm:px-6">
                    <div class="divider my-2 text-xs text-base-content/40">Today</div>

                    <div class="chat chat-start" x-show="! hasMessages" x-cloak>
                        <div class="chat-image avatar avatar-placeholder">
                            <div class="w-8 rounded-full bg-primary text-xs font-semibold text-primary-content">
                                <span>i</span>
                            </div>
                        </div>
                        <div class="chat-header">
                            <span>Assistant</span>
                        </div>
                        <div class="chat-bubble leading-relaxed" x-text="greeting"></div>
                    </div>

                    <template x-for="message in messages" :key="message.id">
                        <div class="chat" :class="message.role === 'client' ? 'chat-end' : 'chat-start'">
                            <div class="chat-image avatar avatar-placeholder">
                                <div
                                    class="w-8 rounded-full text-xs font-semibold"
                                    :class="message.role === 'client'
                                        ? 'bg-base-300 text-base-content/70'
                                        : (message.role === 'admin'
                                            ? 'bg-secondary text-secondary-content'
                                            : 'bg-primary text-primary-content')"
                                >
                                    <span x-text="message.role === 'client'
                                        ? (conversation.client_name?.charAt(0).toUpperCase() ?? 'C')
                                        : 'i'"></span>
                                </div>
                            </div>

                            <div class="chat-header">
                                <span x-text="message.role === 'client'
                                    ? (conversation.client_name ?? 'You')
                                    : (message.role === 'admin' ? 'ionvop' : 'Assistant')"></span>
                                <time class="opacity-50" x-text="message.time"></time>
                            </div>

                            <div
                                class="chat-bubble whitespace-pre-line leading-relaxed"
                                :class="{
                                    'chat-bubble-primary': message.role === 'client',
                                    'chat-bubble-secondary': message.role === 'admin',
                                    'opacity-60': message.pending === true,
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
                        </div>
                    </template>

                    <div class="alert alert-error mt-4 text-sm" x-show="error" x-cloak>
                        <span x-text="error"></span>
                    </div>

                    <p class="mt-6 text-center text-xs text-base-content/40">
                        This conversation is saved in your browser. ionvop reviews every inquiry personally.
                    </p>
                </div>
            </div>

            <footer class="flex-none border-t border-base-300 bg-base-100">
                <div class="mx-auto w-full max-w-3xl px-3 py-3 sm:px-6">
                    <form
                        class="rounded-box border border-base-300 bg-base-200/60 p-2"
                        @submit.prevent="send()"
                    >
                        <label for="chat-message" class="sr-only">Message</label>
                        <textarea
                            id="chat-message"
                            x-ref="input"
                            x-model="draft"
                            rows="1"
                            class="textarea textarea-ghost min-h-11 w-full resize-none px-2 py-1.5 text-sm leading-relaxed"
                            placeholder="Describe your business problem…"
                            maxlength="2000"
                            @input="resize()"
                            @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send(); }"
                        ></textarea>

                        <div class="mt-1 flex items-center gap-1">
                            <input
                                x-ref="fileInput"
                                type="file"
                                class="hidden"
                                :accept="accept"
                                @change="onFileSelected($event)"
                            >
                            <input
                                x-ref="photoInput"
                                type="file"
                                class="hidden"
                                accept="image/*"
                                @change="onFileSelected($event)"
                            >

                            <div class="tooltip tooltip-top" data-tip="Attach a file">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-square btn-sm"
                                    aria-label="Attach a file"
                                    :disabled="sending || resetting"
                                    @click="pickFile()"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M720-330q0 104-73 177T470-80q-104 0-177-73t-73-177v-370q0-75 52.5-127.5T400-880q75 0 127.5 52.5T580-700v350q0 46-32 78t-78 32q-46 0-78-32t-32-78v-370h80v370q0 13 8.5 21.5T470-320q13 0 21.5-8.5T500-350v-350q0-42-29-71t-71-29q-42 0-71 29t-29 71v370q0 71 49.5 120.5T470-160q71 0 120.5-49.5T640-330v-390h80v390Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="tooltip tooltip-top" data-tip="Send a photo">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-square btn-sm"
                                    aria-label="Send a photo"
                                    :disabled="sending || resetting"
                                    @click="pickFile('image')"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm40-80h480L570-480 450-320l-90-120-120 160Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="tooltip tooltip-top" data-tip="Record audio — coming soon">
                                <button type="button" class="btn btn-ghost btn-square btn-sm" aria-label="Record audio" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M480-400q-50 0-85-35t-35-85v-240q0-50 35-85t85-35q50 0 85 35t35 85v240q0 50-35 85t-85 35Zm-40 280v-123q-104-14-172-93t-68-184h80q0 83 58.5 141.5T480-320q83 0 141.5-58.5T680-520h80q0 105-68 184t-172 93v123h-80Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="ml-auto flex items-center gap-2">
                                <span class="hidden text-xs text-base-content/40 sm:inline">Enter to send</span>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-square btn-sm"
                                    aria-label="Send message"
                                    :disabled="! canSend"
                                >
                                    <span class="loading loading-spinner loading-xs" x-show="sending" x-cloak></span>
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 -960 960 960"
                                        fill="currentColor"
                                        class="size-5"
                                        aria-hidden="true"
                                        x-show="! sending"
                                    >
                                        <path d="M120-160v-640l760 320-760 320Zm80-120 474-200-474-200v140l240 60-240 60v140Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="mt-1" x-show="pendingFile" x-cloak>
                            <div class="flex items-center gap-2 rounded-field bg-base-100 px-2 py-1.5">
                                <img
                                    x-show="pendingPreview"
                                    :src="pendingPreview"
                                    alt=""
                                    class="size-8 rounded object-cover"
                                >
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
            </footer>
        </div>

        <div class="drawer-side">
            <label for="chat-info-drawer" aria-label="Close project details" class="drawer-overlay"></label>

            <aside class="flex min-h-full w-80 max-w-[85vw] flex-col gap-5 border-r border-base-300 bg-base-200 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Project details</h2>
                    <span class="badge badge-ghost badge-sm" x-text="conversation.stage"></span>
                </div>

                <div class="rounded-box bg-base-100 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">Conversation</p>
                    <p
                        class="mt-1 font-mono text-sm"
                        x-text="conversation.reference ? `#${conversation.reference}` : 'Not started yet'"
                    ></p>

                    <p class="mt-3 text-xs uppercase tracking-wide text-base-content/50">Client</p>
                    <p class="mt-1 text-sm font-medium" x-text="conversation.client_name ?? 'Not introduced yet'"></p>
                    <p
                        class="truncate text-sm text-base-content/60"
                        x-show="conversation.client_email"
                        x-text="conversation.client_email"
                    ></p>
                    <p class="mt-3 text-xs leading-relaxed text-base-content/40" x-show="! conversation.client_email">
                        Share an email or social account so ionvop can reach you if you lose this conversation.
                    </p>
                </div>

                <div>
                    <p class="mb-2 text-xs uppercase tracking-wide text-base-content/50">Project status</p>
                    <ul class="steps steps-vertical text-xs [&_.step]:min-h-10">
                        <template x-for="(stage, index) in stages" :key="stage">
                            <li
                                class="step"
                                :class="index <= conversation.stage_index ? 'step-primary' : ''"
                                x-text="stage"
                            ></li>
                        </template>
                    </ul>
                </div>

                <div class="rounded-box bg-base-100 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs uppercase tracking-wide text-base-content/50">Payment</p>
                        <span
                            class="badge badge-warning badge-sm"
                            x-show="conversation.amount_due"
                            x-text="`${conversation.currency} ${Number(conversation.amount_due).toLocaleString()} due`"
                        ></span>
                    </div>

                    <template x-if="conversation.first_month_amount">
                        <div>
                            <p class="mt-3 text-xs text-base-content/60">First month</p>
                            <p
                                class="text-2xl font-bold tracking-tight"
                                x-text="`${conversation.currency} ${Number(conversation.first_month_amount).toLocaleString()}`"
                            ></p>

                            <progress
                                class="progress progress-primary mt-3"
                                :value="conversation.amount_paid ?? 0"
                                :max="conversation.first_month_amount"
                            ></progress>

                            <div class="mt-2 flex justify-between gap-2 text-xs text-base-content/60">
                                <span x-text="`${conversation.currency} ${Number(conversation.amount_paid ?? 0).toLocaleString()} paid`"></span>
                                <span x-text="`${conversation.currency} ${Number(conversation.amount_due ?? 0).toLocaleString()} on delivery`"></span>
                            </div>

                            <p class="mt-3 text-xs text-base-content/40" x-show="conversation.monthly_amount">
                                <span x-text="`${conversation.currency} ${Number(conversation.monthly_amount ?? 0).toLocaleString()}`"></span>
                                per month after the first month.
                            </p>
                        </div>
                    </template>

                    <p class="mt-3 text-xs leading-relaxed text-base-content/40" x-show="! conversation.first_month_amount">
                        No payment arrangement yet. ionvop will confirm pricing before any work starts.
                    </p>
                </div>

                <div class="rounded-box bg-base-100 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">Contract</p>

                    <dl class="mt-3 space-y-2 text-sm" x-show="conversation.contract_status">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/60">Status</dt>
                            <dd><span class="badge badge-soft badge-success badge-sm" x-text="conversation.contract_status"></span></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/60">Started</dt>
                            <dd x-text="conversation.contract_started_at ?? '—'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/60">Renews</dt>
                            <dd x-text="conversation.contract_renews_at ?? '—'"></dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-xs leading-relaxed text-base-content/40" x-show="! conversation.contract_status">
                        No contract yet. Nothing is agreed until ionvop confirms the scope and terms.
                    </p>
                </div>

                <div class="rounded-box bg-base-100 p-4" x-show="conversation.reference" x-cloak>
                    <p class="text-xs uppercase tracking-wide text-base-content/50">Danger zone</p>

                    <button
                        type="button"
                        class="btn btn-error btn-outline btn-sm mt-3 w-full"
                        @click="confirmReset()"
                    >
                        Start a new conversation
                    </button>

                    <p class="mt-2 text-[0.65rem] leading-relaxed text-base-content/40">
                        This removes the conversation from this browser. You will not be able to read it again,
                        even though ionvop keeps a copy for follow-up.
                    </p>
                </div>

                <p class="mt-auto text-xs leading-relaxed text-base-content/40">
                    This conversation is saved in your browser. Clearing browser data removes access to it,
                    so keep the email above reachable.
                </p>
            </aside>
        </div>

        <dialog id="chat-reset-dialog" class="modal">
            <div class="modal-box">
                <h3 class="text-lg font-bold text-error">Start a new conversation?</h3>

                <p class="py-4 text-sm leading-relaxed text-base-content/70">
                    You are about to lose access to this conversation and every message in it.
                    It will be removed from this browser and you will not be able to read it again.
                </p>

                <div class="alert alert-error text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5 shrink-0" aria-hidden="true">
                        <path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z" />
                    </svg>
                    <span>
                        This cannot be undone from your side. ionvop still has a copy, so reach out by email
                        if you need anything from this conversation later.
                    </span>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost btn-sm">Cancel</button>
                    </form>

                    <button type="button" class="btn btn-error btn-sm" :disabled="resetting" @click="reset()">
                        <span class="loading loading-spinner loading-xs" x-show="resetting" x-cloak></span>
                        Start a new conversation
                    </button>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <dialog id="chat-lightbox" class="modal">
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
    </div>
@endsection
