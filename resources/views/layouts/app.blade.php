<!DOCTYPE html>
<html lang="en" class="scroll-smooth" x-data x-bind:class="$store.ui.dark ? 'dark' : ''">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ \App\Models\Setting::get('company_name', config('app.name')) }}</title>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💰</text></svg>">

    {{-- Pre-built CSS (no npm needed). Run `npm run css` after editing Tailwind classes. --}}
    <link rel="stylesheet" href="{{ asset('public/css/app.css') }}">

    <script>
        // Set the theme before paint so there is no white flash
        (function () {
            try {
                var d = localStorage.getItem('theme');
                if (d === 'dark' || (!d && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.14.1/cdn.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.3/chart.umd.min.js"></script>
    @stack('head')
</head>

<body class="min-h-screen">
<div x-data="shell()" x-init="init()" class="flex min-h-screen">

    {{-- ============ SIDEBAR ============ --}}
    <aside
        class="no-print fixed inset-y-0 left-0 z-40 flex w-[268px] flex-col bg-ink-950 text-ink-200
               transition-transform duration-200 lg:translate-x-0 dark:border-r dark:border-ink-800"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        @include('partials.sidebar')
    </aside>

    {{-- backdrop (mobile) --}}
    <div x-show="open" x-cloak @click="open = false"
         class="fixed inset-0 z-30 bg-ink-950/60 backdrop-blur-sm lg:hidden"></div>

    {{-- ============ MAIN ============ --}}
    <div class="flex min-w-0 flex-1 flex-col lg:ml-[268px]">

        @include('partials.topbar')

        <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-[1500px] animate-fade-in">
                @include('partials.alerts')
                @yield('content')
            </div>
        </main>

        <footer class="no-print border-t border-ink-200 px-6 py-4 text-xs text-ink-500 dark:border-ink-800">
            <div class="mx-auto flex max-w-[1500px] flex-wrap items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} {{ \App\Models\Setting::get('company_name', 'Scheme ERP') }}</span>
                <span>Gold Scheme ERP · Laravel {{ app()->version() }}</span>
            </div>
        </footer>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('ui', {
            dark: document.documentElement.classList.contains('dark'),
            toggle() {
                this.dark = !this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch (e) {}
            },
        });
    });

    function shell() {
        return {
            open: false,
            init() {
                this.open = window.innerWidth >= 1024;
                window.addEventListener('resize', () => { if (window.innerWidth >= 1024) this.open = true; });
            },
        };
    }

    // Chart.js shared defaults
    window.chartTheme = () => {
        const dark = document.documentElement.classList.contains('dark');
        return {
            grid: dark ? 'rgba(255,255,255,.07)' : 'rgba(15,23,42,.07)',
            text: dark ? '#8591ab' : '#667391',
        };
    };
</script>

@stack('scripts')
</body>
</html>
