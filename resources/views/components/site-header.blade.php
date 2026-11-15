<header class="sticky top-0 z-50 border-b border-base-300 bg-base-100/85 backdrop-blur-md" x-data="{ open: false }">
    <div class="navbar mx-auto w-full max-w-6xl gap-2 px-4 sm:px-6">
        <div class="navbar-start">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid size-9 -skew-x-6 place-items-center rounded-field bg-primary text-primary-content shadow-lg shadow-primary/40">
                    <span class="skew-x-6 text-lg font-bold leading-none">i</span>
                </span>
                <span class="text-lg font-bold tracking-tight">ionvop Tech Solutions</span>
            </a>
        </div>

        <div class="navbar-center hidden lg:flex">
            <ul class="menu menu-horizontal gap-1 px-1">
                <li><a href="{{ route('home') }}#services">Services</a></li>
                <li><a href="{{ route('home') }}#portfolio">Portfolio</a></li>
                <li><a href="{{ route('home') }}#contact">Contact</a></li>
            </ul>
        </div>

        <div class="navbar-end gap-2">
            <a href="{{ route('chat') }}" class="btn btn-primary btn-sm sm:btn-md">
                Start a chat
            </a>

            <button
                type="button"
                class="btn btn-ghost btn-square lg:hidden"
                aria-label="Toggle navigation"
                aria-controls="mobile-navigation"
                :aria-expanded="open"
                @click="open = !open"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="size-6" aria-hidden="true">
                    <path x-show="! open" d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z" />
                    <path x-show="open" x-cloak d="M256-200l-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                </svg>
            </button>
        </div>
    </div>

    <div id="mobile-navigation" x-show="open" x-cloak x-transition.opacity class="border-t border-base-300 lg:hidden">
        <ul class="menu mx-auto w-full max-w-6xl gap-1 px-4 py-3 sm:px-6">
            <li><a href="{{ route('home') }}#services" @click="open = false">Services</a></li>
            <li><a href="{{ route('home') }}#portfolio" @click="open = false">Portfolio</a></li>
            <li><a href="{{ route('home') }}#contact" @click="open = false">Contact</a></li>
            <li><a href="{{ route('chat') }}" @click="open = false">Chat</a></li>
        </ul>
    </div>
</header>
