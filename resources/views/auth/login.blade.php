<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ \App\Models\Setting::get('company_name', config('app.name')) }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💰</text></svg>">
    <link rel="stylesheet" href="{{ asset('public/css/app.css') }}">
    <script>
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
</head>

<body class="min-h-screen bg-ink-100 dark:bg-ink-950">
@php
    $company = \App\Models\Setting::get('company_name', 'Scheme ERP');
    $tagline = \App\Models\Setting::get('company_tagline', 'Gold & Diamonds');
@endphp

<div class="grid min-h-screen lg:grid-cols-2">

    {{-- ---------- Brand panel ---------- --}}
    <div class="relative hidden overflow-hidden bg-ink-950 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(212,134,29,.35),transparent_55%),radial-gradient(ellipse_at_bottom_right,rgba(155,76,23,.35),transparent_55%)]"></div>
        <div class="absolute inset-0 opacity-[0.06]"
             style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px"></div>

        <div class="relative flex h-full flex-col justify-between p-12">
            <div class="flex items-center gap-3">
                <div class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-700 font-black text-white shadow-lg">
                    {{ strtoupper(substr($company, 0, 2)) }}
                </div>
                <div>
                    <div class="text-lg font-bold text-white">{{ $company }}</div>
                    <div class="text-xs text-ink-400">{{ $tagline }}</div>
                </div>
            </div>

            <div class="max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight text-white">
                    Gold Scheme <span class="text-brand-400">ERP</span>
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-ink-400">
                    Customers, agents, schemes and collections in one place — with a complete
                    double-entry ledger behind every rupee.
                </p>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    @foreach ([
                        ['Chart of Accounts', '3-level: base → group → ledger'],
                        ['Day Book', 'Every voucher, both sides'],
                        ['Trial Balance', 'Always Dr = Cr'],
                        ['P&L + Balance Sheet', 'Live from the ledger'],
                    ] as [$t, $d])
                        <div class="rounded-xl border border-white/10 bg-white/5 p-3.5 backdrop-blur">
                            <div class="text-[13px] font-semibold text-white">{{ $t }}</div>
                            <div class="mt-0.5 text-[11px] leading-snug text-ink-400">{{ $d }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="text-xs text-ink-600">&copy; {{ date('Y') }} {{ $company }}. All rights reserved.</p>
        </div>
    </div>

    {{-- ---------- Form ---------- --}}
    <div class="flex items-center justify-center p-6 sm:p-10">
        <div class="w-full max-w-[400px]">

            <div class="mb-8 lg:hidden">
                <div class="mb-3 grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-700 font-black text-white">
                    {{ strtoupper(substr($company, 0, 2)) }}
                </div>
                <div class="text-xl font-bold">{{ $company }}</div>
                <div class="text-xs text-ink-500">{{ $tagline }}</div>
            </div>

            <h1 class="text-2xl font-bold tracking-tight">Sign in</h1>
            <p class="mt-1.5 text-sm text-ink-500">Enter your credentials to access the dashboard.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label class="label" for="login">Username or Email</label>
                    <input id="login" name="login" value="{{ old('login') }}" required autofocus
                           class="input" placeholder="admin" autocomplete="username">
                </div>

                <div x-data="{ show: false }">
                    <label class="label" for="password">Password</label>
                    <div class="relative">
                        <input id="password" name="password" required
                               :type="show ? 'text' : 'password'"
                               class="input pr-11" placeholder="••••••••" autocomplete="current-password">
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-ink-400 hover:text-ink-600">
                            <svg x-show="!show" class="h-4.5 w-4.5" style="height:18px;width:18px" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg x-show="show" x-cloak style="height:18px;width:18px" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path d="M3 3l18 18M10.6 10.6A3 3 0 0013.4 13.4M9.9 5.2A9.5 9.5 0 0112 5c6.4 0 10 7 10 7a17 17 0 01-3.2 4.1M6.2 6.2A17 17 0 002 12s3.6 7 10 7c1.3 0 2.4-.2 3.5-.6" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <input type="checkbox" name="remember" value="1"
                           class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                    Remember me
                </label>

                <button class="btn-primary w-full py-2.5">Sign in</button>
            </form>

            @php
                $apkPath = \App\Models\Setting::get('android_apk_path', 'releases/scheme-erp.apk');
                $apkAvailable = \Illuminate\Support\Facades\Storage::disk('public')->exists($apkPath);
            @endphp
            @if ($apkAvailable)
                <a href="{{ route('app.download') }}"
                   class="btn-outline mt-4 flex w-full items-center justify-center py-2.5">
                    Download Android App
                </a>
            @endif

            @if (app()->environment('local'))
                <div class="mt-6 rounded-xl border border-ink-200 bg-white p-3.5 text-xs dark:border-ink-800 dark:bg-ink-900">
                    <p class="mb-1.5 font-semibold text-ink-600 dark:text-ink-300">Demo logins</p>
                    <p class="text-ink-500">Admin — <span class="kbd">admin</span> / <span class="kbd">admin@123</span></p>
                    <p class="mt-1 text-ink-500">Accountant — <span class="kbd">accounts</span> / <span class="kbd">accounts@123</span></p>
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
