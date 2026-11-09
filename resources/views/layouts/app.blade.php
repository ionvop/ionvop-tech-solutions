<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="ionvop" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name'))</title>
        <meta name="description" content="@yield('description', 'Custom software and websites for local businesses in Tagum City and Nabunturan, Davao Region.')">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-base-100 font-sans text-base-content antialiased">
        <x-site-header />

        <main class="flex flex-1 flex-col">
            @yield('content')
        </main>

        <x-site-footer />
    </body>
</html>
