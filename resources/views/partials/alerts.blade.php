@if (session('success'))
    <div x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 5000)"
         class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9"/>
        </svg>
        <p class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</p>
        <button @click="s = false" class="text-emerald-500 hover:text-emerald-700">&times;</button>
    </div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 dark:border-rose-500/20 dark:bg-rose-500/10">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01" stroke-linecap="round"/>
            </svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-rose-800 dark:text-rose-300">Please check the following:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5 text-sm text-rose-700 dark:text-rose-400">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
