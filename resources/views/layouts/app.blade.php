<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0a0f">
        <meta name="description" content="@yield('description', 'Custom software and websites for local businesses and teams.')">
        <title>@yield('title', 'Ionvop | Software Solutions')</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-ink font-sans text-base-content antialiased">
        <a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:bg-mint focus:p-3 focus:text-ink" href="#main-content">Skip to content</a>

        <header class="site-header">
            <div class="navbar site-container site-nav">
                <div class="navbar-start">
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Ionvop home">
                        <span class="brand-mark" aria-hidden="true">I.</span>
                        <span class="brand-copy">
                            <span>IONVOP</span>
                            <small>SOFTWARE STUDIO</small>
                        </span>
                    </a>
                </div>
                <div class="navbar-center">
                    <nav class="site-nav-links" aria-label="Main navigation">
                        <a href="{{ route('home') }}#services">Services</a>
                        <a href="{{ route('home') }}#work">Selected work</a>
                        <a href="{{ route('home') }}#contact">Contact</a>
                    </nav>
                </div>
                <div class="navbar-end">
                    <a class="btn btn-secondary btn-sm md:btn-md" href="{{ route('chat') }}">Start a conversation</a>
                </div>
            </div>
            <nav class="site-mobile-links site-container" aria-label="Mobile navigation">
                <a href="{{ route('home') }}#services">Services</a>
                <a href="{{ route('home') }}#work">Selected work</a>
                <a href="{{ route('home') }}#contact">Contact</a>
            </nav>
        </header>

        @yield('content')

        <footer class="site-footer site-container">
            <p>&copy; {{ date('Y') }} Ionvop. Built around your work.</p>
            <nav class="footer-links" aria-label="Contact links">
                <a href="mailto:ionvop@gmail.com">Email</a>
                <a href="https://facebook.com/ionvop.omadle" target="_blank" rel="noopener noreferrer">Facebook</a>
                <a href="https://discord.com/users/301203021608779776" target="_blank" rel="noopener noreferrer">Discord</a>
            </nav>
        </footer>
    </body>
</html>