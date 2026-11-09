@props([
    'title',
    'description',
    'eyebrow' => 'Coming soon',
])

<section class="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center px-4 py-24 text-center sm:px-6">
    <span class="badge badge-soft badge-secondary">{{ $eyebrow }}</span>

    <h1 class="mt-6 text-3xl font-bold tracking-tight sm:text-4xl">{{ $title }}</h1>

    <p class="mt-4 max-w-xl text-base leading-relaxed text-base-content/70">{{ $description }}</p>

    <div class="mt-10 flex flex-col gap-3 sm:flex-row">
        <a href="{{ route('home') }}" class="btn btn-primary">Back to home</a>
        <a href="mailto:ionvop@gmail.com" class="btn btn-ghost">Email me instead</a>
    </div>
</section>
