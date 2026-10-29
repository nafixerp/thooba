@props(['action' => null])
<form method="GET" action="{{ $action }}" class="card mb-5 flex flex-wrap items-end gap-3 p-4 no-print">
    {{ $slot }}
</form>
