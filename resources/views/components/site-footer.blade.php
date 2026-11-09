<footer class="border-t border-base-300 bg-base-200">
    <div class="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6">
        <div class="footer sm:footer-horizontal">
            <aside class="max-w-xs">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 -skew-x-6 place-items-center rounded-field bg-primary text-primary-content">
                        <span class="skew-x-6 text-lg font-bold leading-none">i</span>
                    </span>
                    <span class="text-lg font-bold tracking-tight">ionvop</span>
                </div>
                <p class="mt-4 text-sm text-base-content/60">
                    Custom software and websites for businesses in Tagum City, Nabunturan, and beyond.
                </p>
            </aside>

            <nav>
                <h6 class="footer-title">Pages</h6>
                <a href="{{ route('home') }}" class="link link-hover">Home</a>
                <a href="{{ route('home') }}#services" class="link link-hover">Services &amp; pricing</a>
                <a href="{{ route('home') }}#portfolio" class="link link-hover">Portfolio</a>
                <a href="{{ route('chat') }}" class="link link-hover">Chat</a>
            </nav>

            <nav>
                <h6 class="footer-title">Contact</h6>
                <a href="mailto:ionvop@gmail.com" class="link link-hover">ionvop@gmail.com</a>
                <a href="https://github.com/ionvop" target="_blank" rel="noopener noreferrer" class="link link-hover">GitHub</a>
                <a href="https://discord.com/users/301203021608779776" target="_blank" rel="noopener noreferrer" class="link link-hover">Discord</a>
            </nav>
        </div>

        <div class="mt-12 flex flex-col gap-2 border-t border-base-300 pt-6 text-sm text-base-content/50 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} ionvop. All rights reserved.</p>
            <a href="{{ route('admin') }}" class="link link-hover">Admin</a>
        </div>
    </div>
</footer>
