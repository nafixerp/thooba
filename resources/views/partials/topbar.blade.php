@php
    $rate = \App\Models\MetalRate::latest_rate();
    $pendingCount = \App\Models\Payment::where('status', 'PENDING')->count();
@endphp

<header class="no-print sticky top-0 z-20 border-b border-ink-200/80 bg-white/80 backdrop-blur-md dark:border-ink-800 dark:bg-ink-900/80">
    <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">

        <button @click="open = !open" class="btn-ghost btn-icon lg:hidden">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
            </svg>
        </button>

        <div class="min-w-0 flex-1">
            <h1 class="truncate text-[17px] font-semibold tracking-tight">@yield('title', 'Dashboard')</h1>
            @hasSection('subtitle')
                <p class="truncate text-xs text-ink-500">@yield('subtitle')</p>
            @endif
        </div>

        {{-- Live gold rate --}}
        @if ($rate > 0)
            <div class="hidden items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 md:flex dark:border-brand-500/20 dark:bg-brand-500/10">
                <span class="h-2 w-2 animate-pulse rounded-full bg-brand-500"></span>
                <span class="text-[11px] font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-400">Gold 22K</span>
                <span class="font-mono text-sm font-bold text-brand-800 dark:text-brand-300">{{ cur($rate) }}/g</span>
            </div>
        @endif

        {{-- Pending payments --}}
        <a href="{{ route('admin.payments.pending') }}" class="btn-ghost btn-icon relative" title="Pending approvals">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            @if ($pendingCount)
                <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                    {{ $pendingCount > 99 ? '99+' : $pendingCount }}
                </span>
            @endif
        </a>

        {{-- Theme --}}
        <button @click="$store.ui.toggle()" class="btn-ghost btn-icon" title="Toggle theme">
            <svg x-show="!$store.ui.dark" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg x-show="$store.ui.dark" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke-linecap="round"/>
            </svg>
        </button>

        {{-- User --}}
        <div x-data="{ o: false }" class="relative">
            <button @click="o = !o" class="flex items-center gap-2 rounded-lg py-1.5 pl-1.5 pr-2 hover:bg-ink-100 dark:hover:bg-ink-800">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-xs font-bold text-white">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                </span>
                <span class="hidden text-left sm:block">
                    <span class="block text-[13px] font-semibold leading-tight">{{ auth()->user()->name }}</span>
                    <span class="block text-[11px] leading-tight text-ink-500">{{ ucfirst(strtolower(auth()->user()->role)) }}</span>
                </span>
                <svg class="h-4 w-4 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div x-show="o" x-cloak @click.outside="o = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-ink-200 bg-white p-1.5 shadow-pop dark:border-ink-700 dark:bg-ink-900">
                <div class="px-3 py-2">
                    <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</div>
                </div>
                <div class="my-1 h-px bg-ink-100 dark:bg-ink-800"></div>
                <a href="{{ route('admin.password.edit') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-ink-100 dark:hover:bg-ink-800">Change Password</a>
                <a href="{{ route('admin.settings') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-ink-100 dark:hover:bg-ink-800">Company Settings</a>
                <div class="my-1 h-px bg-ink-100 dark:bg-ink-800"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
