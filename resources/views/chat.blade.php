@extends('layouts.app')

@section('title', 'Chat — ionvop')
@section('description', 'Discuss your business problem, share requirements, and follow your project status.')

@section('fullscreen', 'true')

@php
    $conversation = [
        'reference' => 'a3f9c1',
        'client' => [
            'name' => 'Jomar',
            'email' => 'jomar@example.com',
            'started_on' => 'Nov 7, 2026',
        ],
        'stage' => 'Development',
        'payment' => [
            'first_month' => 2000,
            'monthly' => 1000,
            'paid' => 500,
            'due' => 1500,
        ],
        'contract' => [
            'status' => 'Active',
            'started_on' => 'Nov 7, 2026',
            'renews_on' => 'Dec 7, 2026',
        ],
    ];

    $stages = ['Inquiry', 'Planning', 'Development', 'Delivery'];

    $currentStage = array_search($conversation['stage'], $stages, true);
    $currentStage = $currentStage === false ? 0 : $currentStage;

    $messages = [
        [
            'from' => 'assistant',
            'type' => 'text',
            'time' => '9:12 AM',
            'text' => "Hi! I'm the assistant that takes first inquiries while ionvop is away. What should I call you, and what does your business do?",
        ],
        [
            'from' => 'client',
            'type' => 'text',
            'time' => '9:14 AM',
            'text' => 'I\'m Jomar. We run a small hardware store in Nabunturan.',
        ],
        [
            'from' => 'assistant',
            'type' => 'text',
            'time' => '9:14 AM',
            'text' => 'Nice to meet you, Jomar. I labeled this conversation "Jomar — hardware store". Which part of the day-to-day takes the most time?',
        ],
        [
            'from' => 'client',
            'type' => 'text',
            'time' => '9:16 AM',
            'text' => 'Stock counts. We still write sales in a notebook and only notice we are out of an item when a customer asks for it.',
        ],
        [
            'from' => 'client',
            'type' => 'image',
            'time' => '9:17 AM',
            'text' => 'This is what our stock notebook looks like.',
            'attachment' => [
                'src' => asset('assets/inventory.webp'),
                'alt' => 'Photo of the hardware store stock notebook',
            ],
        ],
        [
            'from' => 'assistant',
            'type' => 'text',
            'time' => '9:18 AM',
            'text' => 'That is a common one. A small inventory system could update stock as you record a sale and warn you before an item runs out. Do you also track supplier deliveries?',
        ],
        [
            'from' => 'client',
            'type' => 'location',
            'time' => '9:21 AM',
            'text' => 'Nabunturan, Davao de Oro',
        ],
        [
            'from' => 'client',
            'type' => 'audio',
            'time' => '9:22 AM',
            'duration' => '0:12',
        ],
        [
            'from' => 'client',
            'type' => 'file',
            'time' => '9:23 AM',
            'attachment' => [
                'name' => 'price-list-october.xlsx',
                'meta' => 'Excel · 48 KB',
            ],
        ],
        [
            'from' => 'assistant',
            'type' => 'text',
            'time' => '9:24 AM',
            'text' => 'Thanks, Jomar. I will pass this to ionvop with a summary of the problem, the notebook photo, and your price list. Expect a reply within a day.',
        ],
    ];
@endphp

@section('content')
    <div class="drawer min-h-0 flex-1 grid-rows-[minmax(0,1fr)] overflow-hidden lg:drawer-open">
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
                        <p class="truncate text-sm font-semibold">Project conversation</p>
                        <p class="flex items-center gap-1.5 text-xs text-base-content/60">
                            <span class="status status-success status-xs"></span>
                            Replies within a day
                        </p>
                    </div>

                    <a href="{{ route('home') }}" class="btn btn-ghost btn-sm">Back to site</a>
                </div>
            </header>

            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                role="log"
                aria-live="polite"
                aria-label="Conversation"
            >
                <div class="mx-auto flex w-full max-w-3xl flex-col px-3 py-4 sm:px-6">
                    <div class="divider my-2 text-xs text-base-content/40">Today</div>

                    @foreach ($messages as $message)
                        @php
                            $isClient = $message['from'] === 'client';
                        @endphp

                        <div @class(['chat', 'chat-end' => $isClient, 'chat-start' => ! $isClient])>
                            <div class="chat-image avatar avatar-placeholder">
                                <div @class([
                                    'w-8 rounded-full text-xs font-semibold',
                                    'bg-primary text-primary-content' => ! $isClient,
                                    'bg-base-300 text-base-content/70' => $isClient,
                                ])>
                                    <span>{{ $isClient ? 'J' : 'i' }}</span>
                                </div>
                            </div>

                            <div class="chat-header">
                                <span>{{ $isClient ? $conversation['client']['name'] : 'Assistant' }}</span>
                                <time class="opacity-50">{{ $message['time'] }}</time>
                            </div>

                            @switch($message['type'])
                                @case('image')
                                    <div @class(['chat-bubble max-w-xs p-2', 'chat-bubble-primary' => $isClient])>
                                        <img
                                            src="{{ $message['attachment']['src'] }}"
                                            alt="{{ $message['attachment']['alt'] }}"
                                            loading="lazy"
                                            class="rounded-field"
                                        >
                                        <p class="mt-2 px-1 text-xs leading-relaxed opacity-70">{{ $message['text'] }}</p>
                                    </div>
                                    @break

                                @case('location')
                                    <div @class(['chat-bubble', 'chat-bubble-primary' => $isClient])>
                                        <p class="flex items-center gap-2 text-sm font-medium">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-4 shrink-0" aria-hidden="true">
                                                <path d="M480-480q33 0 56.5-23.5T560-560q0-33-23.5-56.5T480-640q-33 0-56.5 23.5T400-560q0 33 23.5 56.5T480-480Zm0 294q122-112 181-203.5T720-560q0-100-68-170t-172-70q-104 0-172 70t-68 170q0 69 59 160.5T480-186Z" />
                                            </svg>
                                            {{ $message['text'] }}
                                        </p>
                                        <p class="mt-1 text-xs opacity-70">Shared location</p>
                                    </div>
                                    @break

                                @case('audio')
                                    <div @class(['chat-bubble flex items-center gap-3', 'chat-bubble-primary' => $isClient])>
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-base-100/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-4" aria-hidden="true">
                                                <path d="M320-200v-560l440 280-440 280Z" />
                                            </svg>
                                        </span>
                                        <span class="flex items-end gap-0.5" aria-hidden="true">
                                            <span class="h-2 w-0.5 rounded-full bg-current opacity-60"></span>
                                            <span class="h-4 w-0.5 rounded-full bg-current opacity-60"></span>
                                            <span class="h-3 w-0.5 rounded-full bg-current opacity-60"></span>
                                            <span class="h-5 w-0.5 rounded-full bg-current opacity-60"></span>
                                            <span class="h-2 w-0.5 rounded-full bg-current opacity-60"></span>
                                            <span class="h-4 w-0.5 rounded-full bg-current opacity-60"></span>
                                            <span class="h-3 w-0.5 rounded-full bg-current opacity-60"></span>
                                        </span>
                                        <span class="text-xs opacity-70">{{ $message['duration'] }}</span>
                                    </div>
                                    @break

                                @case('file')
                                    <div @class(['chat-bubble flex items-center gap-3', 'chat-bubble-primary' => $isClient])>
                                        <span class="grid size-9 shrink-0 place-items-center rounded-field bg-base-100/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-4" aria-hidden="true">
                                                <path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520Z" />
                                            </svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium">{{ $message['attachment']['name'] }}</span>
                                            <span class="block text-xs opacity-70">{{ $message['attachment']['meta'] }}</span>
                                        </span>
                                    </div>
                                    @break

                                @default
                                    <div @class(['chat-bubble leading-relaxed', 'chat-bubble-primary' => $isClient])>{{ $message['text'] }}</div>
                            @endswitch
                        </div>
                    @endforeach

                    <div class="chat chat-start">
                        <div class="chat-image avatar avatar-placeholder">
                            <div class="w-8 rounded-full bg-primary text-xs font-semibold text-primary-content">
                                <span>i</span>
                            </div>
                        </div>
                        <div class="chat-header">
                            <span>Assistant</span>
                        </div>
                        <div class="chat-bubble flex items-center gap-1">
                            <span class="loading loading-dots loading-sm"></span>
                            <span class="text-xs opacity-70">typing</span>
                        </div>
                    </div>

                    <p class="mt-6 text-center text-xs text-base-content/40">
                        Sample conversation for layout preview. Nothing is sent yet.
                    </p>
                </div>
            </div>

            <footer class="flex-none border-t border-base-300 bg-base-100">
                <div class="mx-auto w-full max-w-3xl px-3 py-3 sm:px-6">
                    <div class="rounded-box border border-base-300 bg-base-200/60 p-2">
                        <label for="chat-message" class="sr-only">Message</label>
                        <textarea
                            id="chat-message"
                            rows="1"
                            class="textarea textarea-ghost min-h-11 w-full resize-none px-2 py-1.5 text-sm leading-relaxed"
                            placeholder="Describe your business problem…"
                        ></textarea>

                        <div class="mt-1 flex items-center gap-1">
                            <div class="tooltip tooltip-top" data-tip="Attach a file — coming soon">
                                <button type="button" class="btn btn-ghost btn-square btn-sm" aria-label="Attach a file">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M720-330q0 104-73 177T470-80q-104 0-177-73t-73-177v-370q0-75 52.5-127.5T400-880q75 0 127.5 52.5T580-700v350q0 46-32 78t-78 32q-46 0-78-32t-32-78v-370h80v370q0 13 8.5 21.5T470-320q13 0 21.5-8.5T500-350v-350q0-42-29-71t-71-29q-42 0-71 29t-29 71v370q0 71 49.5 120.5T470-160q71 0 120.5-49.5T640-330v-390h80v390Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="tooltip tooltip-top" data-tip="Send a photo — coming soon">
                                <button type="button" class="btn btn-ghost btn-square btn-sm" aria-label="Send a photo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm40-80h480L570-480 450-320l-90-120-120 160Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="tooltip tooltip-top" data-tip="Record audio — coming soon">
                                <button type="button" class="btn btn-ghost btn-square btn-sm" aria-label="Record audio">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M480-400q-50 0-85-35t-35-85v-240q0-50 35-85t85-35q50 0 85 35t35 85v240q0 50-35 85t-85 35Zm-40 280v-123q-104-14-172-93t-68-184h80q0 83 58.5 141.5T480-320q83 0 141.5-58.5T680-520h80q0 105-68 184t-172 93v123h-80Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="tooltip tooltip-top" data-tip="Share your location — coming soon">
                                <button type="button" class="btn btn-ghost btn-square btn-sm" aria-label="Share your location">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M480-480q33 0 56.5-23.5T560-560q0-33-23.5-56.5T480-640q-33 0-56.5 23.5T400-560q0 33 23.5 56.5T480-480Zm0 294q122-112 181-203.5T720-560q0-100-68-170t-172-70q-104 0-172 70t-68 170q0 69 59 160.5T480-186Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="ml-auto flex items-center gap-2">
                                <span class="hidden text-xs text-base-content/40 sm:inline">Enter to send</span>
                                <button type="button" class="btn btn-primary btn-square btn-sm" aria-label="Send message">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                                        <path d="M120-160v-640l760 320-760 320Zm80-120 474-200-474-200v140l240 60-240 60v140Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <div class="drawer-side">
            <label for="chat-info-drawer" aria-label="Close project details" class="drawer-overlay"></label>

            <aside class="flex min-h-full w-80 max-w-[85vw] flex-col gap-5 border-r border-base-300 bg-base-200 p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Project details</h2>
                    <span class="badge badge-ghost badge-sm">Sample</span>
                </div>

                <div class="rounded-box bg-base-100 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">Conversation</p>
                    <p class="mt-1 font-mono text-sm">#{{ $conversation['reference'] }}</p>
                    <p class="mt-3 text-xs uppercase tracking-wide text-base-content/50">Client</p>
                    <p class="mt-1 text-sm font-medium">{{ $conversation['client']['name'] }}</p>
                    <p class="truncate text-sm text-base-content/60">{{ $conversation['client']['email'] }}</p>
                    <p class="mt-3 text-xs text-base-content/40">Started {{ $conversation['client']['started_on'] }}</p>
                </div>

                <div>
                    <p class="mb-2 text-xs uppercase tracking-wide text-base-content/50">Project status</p>
                    <ul class="steps steps-vertical text-xs [&_.step]:min-h-10">
                        @foreach ($stages as $index => $stage)
                            <li @class(['step', 'step-primary' => $index <= $currentStage])>{{ $stage }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-box bg-base-100 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs uppercase tracking-wide text-base-content/50">Payment</p>
                        <span class="badge badge-warning badge-sm">PHP {{ number_format($conversation['payment']['due']) }} due</span>
                    </div>

                    <p class="mt-3 text-xs text-base-content/60">First month</p>
                    <p class="text-2xl font-bold tracking-tight">PHP {{ number_format($conversation['payment']['first_month']) }}</p>

                    <progress
                        class="progress progress-primary mt-3"
                        value="{{ $conversation['payment']['paid'] }}"
                        max="{{ $conversation['payment']['first_month'] }}"
                    ></progress>

                    <div class="mt-2 flex justify-between gap-2 text-xs text-base-content/60">
                        <span>PHP {{ number_format($conversation['payment']['paid']) }} paid</span>
                        <span>PHP {{ number_format($conversation['payment']['due']) }} on delivery</span>
                    </div>

                    <p class="mt-3 text-xs text-base-content/40">
                        PHP {{ number_format($conversation['payment']['monthly']) }} per month after the first month.
                    </p>
                </div>

                <div class="rounded-box bg-base-100 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">Contract</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/60">Status</dt>
                            <dd><span class="badge badge-soft badge-success badge-sm">{{ $conversation['contract']['status'] }}</span></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/60">Started</dt>
                            <dd>{{ $conversation['contract']['started_on'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/60">Renews</dt>
                            <dd>{{ $conversation['contract']['renews_on'] }}</dd>
                        </div>
                    </dl>
                </div>

                <p class="mt-auto text-xs leading-relaxed text-base-content/40">
                    This conversation is saved in your browser. Clearing browser data removes access to it,
                    so keep the email above reachable.
                </p>
            </aside>
        </div>
    </div>
@endsection
