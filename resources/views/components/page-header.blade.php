@props(['title' => null, 'sub' => null])

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        @if ($title)<h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>@endif
        @if ($sub)<p class="mt-0.5 text-sm text-ink-500">{{ $sub }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
