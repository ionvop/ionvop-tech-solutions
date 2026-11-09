<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="ionvop" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>
        <meta name="description" content="@yield('description', 'Custom software and websites for local businesses in Tagum City and Nabunturan, Davao Region.')">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @hasSection('fullscreen')
        <body class="flex h-dvh flex-col overflow-hidden bg-base-100 font-sans text-base-content antialiased">
            <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @yield('content')
            </main>
        </body>
    @else
        <body class="flex min-h-screen flex-col bg-base-100 font-sans text-base-content antialiased">
            <x-site-header />

            <main class="flex flex-1 flex-col">
                @yield('content')
            </main>

            <x-site-footer />
        </body>
    @endif
</html>
