@props(['label', 'value', 'sub' => null, 'icon' => 'wallet', 'tone' => 'brand', 'href' => null])

@php
    $tones = [
        'brand'   => 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
        'green'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        'blue'    => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400',
        'rose'    => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
        'amber'   => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        'violet'  => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400',
        'slate'   => 'bg-ink-100 text-ink-700 dark:bg-ink-800 dark:text-ink-300',
    ];
    $paths = [
        'wallet'   => 'M3 7a2 2 0 012-2h12a2 2 0 012 2v1h1a1 1 0 011 1v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
        'users'    => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5z',
        'coins'    => 'M12 2c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3zM4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6',
        'layers'   => 'M12 3l9 5-9 5-9-5 9-5zM3 13l9 5 9-5',
        'clock'    => 'M12 7v5l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'alert'    => 'M12 9v4m0 4h.01M10.3 3.9L2.5 17a2 2 0 001.7 3h15.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'trend'    => 'M3 17l6-6 4 4 7-7m0 0h-5m5 0v5',
        'trenddown'=> 'M3 7l6 6 4-4 7 7m0 0h-5m5 0v-5',
        'gem'      => 'M6 3h12l3 6-9 12L3 9l3-6z',
        'bank'     => 'M3 10l9-6 9 6M5 10v9m14-9v9M3 20h18M9 14v5m6-5v5',
        'check'    => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'phone'    => 'M7 3h10a1 1 0 011 1v16a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1zm3 15h4',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="card card-pad flex items-start gap-4 {{ $href ? 'transition hover:-translate-y-0.5 hover:shadow-pop' : '' }}">
    <span class="stat-icon {{ $tones[$tone] ?? $tones['brand'] }}">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="{{ $paths[$icon] ?? $paths['wallet'] }}"/>
        </svg>
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-[11.5px] font-semibold uppercase tracking-wide text-ink-500">{{ $label }}</p>
        <p class="mt-1 text-[20px] font-bold leading-tight tracking-tight tabular-nums">{{ $value }}</p>
        @if ($sub)
            <p class="mt-0.5 truncate text-xs text-ink-500">{!! $sub !!}</p>
        @endif
    </div>
</{{ $tag }}>
