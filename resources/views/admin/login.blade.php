@extends('layouts.app')

@section('title', 'Admin sign-in — ionvop')
@section('description', 'Sign in with the administrator Google account to manage client conversations.')

@section('fullscreen', 'true')

@section('content')
    <div class="flex min-h-0 flex-1 items-center justify-center overflow-y-auto px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center text-center">
                <span class="grid size-12 -skew-x-6 place-items-center rounded-field bg-primary text-primary-content shadow-lg shadow-primary/40">
                    <span class="skew-x-6 text-xl font-bold leading-none">i</span>
                </span>

                <h1 class="mt-5 text-2xl font-bold tracking-tight">Admin dashboard</h1>
                <p class="mt-2 text-sm leading-relaxed text-base-content/60">
                    Sign in with the administrator Google account to review inquiries and manage projects.
                </p>
            </div>

            <div class="card card-border bg-base-200">
                <div class="card-body gap-4">
                    @if (! $configured)
                        <div class="alert alert-warning text-sm" role="alert">
                            <span>
                                Admin sign-in is not configured yet. Set <code class="font-mono">ADMIN_EMAIL</code>,
                                <code class="font-mono">GOOGLE_CLIENT_ID</code>, and
                                <code class="font-mono">GOOGLE_CLIENT_SECRET</code> in the environment file.
                            </span>
                        </div>
                    @endif

                    @error('google')
                        <div class="alert alert-error text-sm" role="alert">
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <a
                        href="{{ route('admin.auth.redirect') }}"
                        @class([
                            'btn btn-primary w-full gap-2',
                            'btn-disabled' => ! $configured,
                        ])
                        @if (! $configured) aria-disabled="true" tabindex="-1" @endif
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true">
                            <path d="M12 11v2.4h6.1c-.3 1.5-1.7 4.4-6.1 4.4-3.7 0-6.7-3-6.7-6.8S8.3 4.2 12 4.2c2.1 0 3.5.9 4.3 1.7l2-1.9C16.9 2.6 14.7 1.6 12 1.6 6.3 1.6 1.7 6.2 1.7 12S6.3 22.4 12 22.4c5.9 0 9.8-4.2 9.8-10 0-.7-.1-1.2-.2-1.7H12Z" />
                        </svg>
                        Continue with Google
                    </a>

                    <p class="text-center text-xs leading-relaxed text-base-content/40">
                        Only the account configured on the server is allowed in.
                    </p>
                </div>
            </div>

            <div class="mt-6 text-center">
                <a href="{{ route('home') }}" class="link link-hover text-sm text-base-content/60">Back to site</a>
            </div>
        </div>
    </div>
@endsection
