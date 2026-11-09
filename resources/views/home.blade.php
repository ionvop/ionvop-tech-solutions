@extends('layouts.app')

@section('title', 'ionvop — Custom software for local businesses')

@section('content')
    <div class="hero relative isolate min-h-[80vh] overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/25 blur-3xl"></div>
            <div class="absolute -bottom-52 -right-24 size-[28rem] rounded-full bg-secondary/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,var(--color-base-300)_1px,transparent_1px),linear-gradient(to_bottom,var(--color-base-300)_1px,transparent_1px)] [background-size:4rem_4rem] opacity-25"></div>
        </div>

        <div class="hero-content max-w-3xl flex-col px-4 py-24 text-center sm:px-6">
            <span class="badge badge-soft badge-accent gap-2">
                <span class="status status-success"></span>
                Tagum City &middot; Nabunturan, Davao Region
            </span>

            <h1 class="mt-6 text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                Software built around how your business
                <span class="text-secondary">actually works</span>
            </h1>

            <p class="mt-6 max-w-2xl text-base leading-relaxed text-base-content/70 sm:text-lg">
                I build custom applications and websites that automate repetitive tasks and streamline
                existing operations &mdash; designed around your real workflow, not a generic template.
            </p>

            <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('chat') }}" class="btn btn-primary btn-lg">
                    Discuss your project
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-5" aria-hidden="true">
                        <path d="M176-183q-20 8-38-3.5T120-220v-180l320-80-320-80v-180q0-22 18-33.5t38-3.5l616 260q25 11 25 37t-25 37L176-183Z" />
                    </svg>
                </a>
                <a href="#portfolio" class="btn btn-ghost btn-lg">See past work</a>
            </div>

            <div class="mt-12 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-base-content/50">
                <span>GCash</span>
                <span class="text-base-content/20">&bull;</span>
                <span>PayPal</span>
                <span class="text-base-content/20">&bull;</span>
                <span>Local clients first</span>
            </div>
        </div>
    </div>

    <section id="services" class="scroll-mt-24 border-t border-base-300 bg-base-200/40">
        <div class="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 lg:py-28">
            <div class="max-w-2xl">
                <span class="badge badge-soft badge-secondary">Services &amp; pricing</span>
                <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">
                    Simple monthly pricing, no surprises
                </h2>
                <p class="mt-4 text-base leading-relaxed text-base-content/70">
                    One flat rate covers development, delivery, and ongoing support. The first month
                    includes building the project; every month after that covers maintenance and updates.
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <div class="card card-border bg-base-100">
                    <div class="card-body gap-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="card-title text-xl">Local clients</h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Tagum City, Davao del Norte &amp; Nabunturan, Davao de Oro
                                </p>
                            </div>
                            <span class="badge badge-primary badge-sm">Priority</span>
                        </div>

                        <div class="rounded-box bg-base-200 p-5">
                            <p class="text-sm text-base-content/60">First month</p>
                            <p class="text-3xl font-bold tracking-tight">PHP 2,000</p>
                            <p class="mt-3 text-sm text-base-content/60">Every month after</p>
                            <p class="text-xl font-semibold">PHP 1,000</p>
                        </div>

                        <ul class="space-y-2 text-sm text-base-content/70">
                            <li class="flex gap-2">
                                <span class="text-accent">&#10003;</span>
                                Pay PHP 500 to start development
                            </li>
                            <li class="flex gap-2">
                                <span class="text-accent">&#10003;</span>
                                Pay the remaining PHP 1,500 on delivery
                            </li>
                            <li class="flex gap-2">
                                <span class="text-accent">&#10003;</span>
                                In-person consultations available
                            </li>
                        </ul>

                        <div class="card-actions mt-auto pt-2">
                            <a href="{{ route('chat') }}" class="btn btn-primary btn-block">Start a conversation</a>
                        </div>
                    </div>
                </div>

                <div class="card card-border bg-base-100">
                    <div class="card-body gap-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="card-title text-xl">International clients</h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Remote projects, any timezone
                                </p>
                            </div>
                            <span class="badge badge-ghost badge-sm">Remote</span>
                        </div>

                        <div class="rounded-box bg-base-200 p-5">
                            <p class="text-sm text-base-content/60">First month</p>
                            <p class="text-3xl font-bold tracking-tight">USD 20</p>
                            <p class="mt-3 text-sm text-base-content/60">Every month after</p>
                            <p class="text-xl font-semibold">USD 10</p>
                        </div>

                        <ul class="space-y-2 text-sm text-base-content/70">
                            <li class="flex gap-2">
                                <span class="text-accent">&#10003;</span>
                                Pay USD 5 to start development
                            </li>
                            <li class="flex gap-2">
                                <span class="text-accent">&#10003;</span>
                                Pay the remaining USD 15 on delivery
                            </li>
                            <li class="flex gap-2">
                                <span class="text-accent">&#10003;</span>
                                Local clients are served first
                            </li>
                        </ul>

                        <div class="card-actions mt-auto pt-2">
                            <a href="{{ route('chat') }}" class="btn btn-ghost btn-block">Start a conversation</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider mt-12 text-sm text-base-content/50">Payments via GCash or PayPal</div>
        </div>
    </section>

    <section id="portfolio" class="scroll-mt-24 border-t border-base-300">
        <div class="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 lg:py-28">
            <div class="max-w-2xl">
                <span class="badge badge-soft badge-secondary">Portfolio</span>
                <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">
                    Projects built for real operations
                </h2>
                <p class="mt-4 text-base leading-relaxed text-base-content/70">
                    Each of these started as a specific problem inside a specific organization &mdash;
                    not as a demo.
                </p>
            </div>

            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <x-portfolio-card
                    tag="Mobile · AI"
                    title="Nowcast — AI-Assisted Weather Application"
                    description="Current weather with AI-generated summaries and activity recommendations based on your location and conditions. Also checks weather at other locations supported by the Google API."
                    image="assets/nowcast.webp"
                    href="https://github.com/ionvop/nowcast"
                    fit="contain"
                />

                <x-portfolio-card
                    tag="Desktop · Inventory"
                    title="Inventory Management System — DRMC Nutrition & Dietetics Department"
                    description="Built around the department's actual operational workflows instead of a generic inventory template, so tracking matches how the team really works."
                    image="assets/inventory.webp"
                    href="https://github.com/ionvop/inventory-management-system"
                />

                <x-portfolio-card
                    tag="Desktop · Attendance"
                    title="Attendance Monitoring System — UMTC Accounting Education Department"
                    description="Barcode-based attendance using student ID cards, running primarily over the local network so it keeps working where internet access is slow or unreliable."
                    image="assets/attendance.webp"
                    href="https://github.com/ionvop/yet-another-attendance-monitoring-system"
                />
            </div>
        </div>
    </section>

    <section id="contact" class="scroll-mt-24 border-t border-base-300 bg-base-200">
        <div class="mx-auto w-full max-w-4xl px-4 py-20 text-center sm:px-6 lg:py-28">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                Tell me what's slowing your business down
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-base-content/70">
                Describe the problem in plain words. We'll work out whether software can fix it,
                what it would take, and what it would cost.
            </p>

            <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ route('chat') }}" class="btn btn-primary btn-lg">Open the chat</a>
                <a href="mailto:ionvop@gmail.com" class="btn btn-ghost btn-lg">ionvop@gmail.com</a>
            </div>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <a href="https://github.com/ionvop" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost">
                    GitHub
                </a>
                <a href="https://discord.com/users/301203021608779776" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost">
                    Discord
                </a>
            </div>
        </div>
    </section>
@endsection
