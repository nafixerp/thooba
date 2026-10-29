@props(['message' => 'No records found', 'cols' => 6])
<tr>
    <td colspan="{{ $cols }}" class="px-4 py-14 text-center">
        <svg class="mx-auto h-10 w-10 text-ink-300 dark:text-ink-700" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0l-2 7H6l-2-7m16 0H4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p class="mt-3 text-sm font-medium text-ink-500">{{ $message }}</p>
        {{ $slot }}
    </td>
</tr>
