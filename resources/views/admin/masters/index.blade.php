@extends('layouts.app')
@section('title', $title)
@section('subtitle', 'Master data')

@php
    /** Resolve "relation.column" style keys */
    $get = function ($row, string $key) {
        $val = $row;
        foreach (explode('.', $key) as $part) {
            $val = is_object($val) ? ($val->{$part} ?? null) : null;
        }
        return $val;
    };
@endphp

@section('content')

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

    {{-- ---------------- LIST ---------------- --}}
    <div class="lg:col-span-2">
        <x-filters :action="route('admin.master.index', $entity)">
            <div class="min-w-[220px] flex-1">
                <label class="label">Search</label>
                <input name="q" value="{{ request('q') }}" class="input" placeholder="Search {{ strtolower($title) }}">
            </div>
            <button class="btn-primary">Search</button>
            @if (request('q'))
                <a href="{{ route('admin.master.index', $entity) }}" class="btn-outline">Clear</a>
            @endif
        </x-filters>

        <div class="card">
            <div class="card-head">
                <h3 class="card-title">{{ $title }}</h3>
                <span class="badge-gray">{{ $rows->total() }} records</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach ($columns as $key => $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr x-data="{ edit: false }">
                                @foreach ($columns as $key => $label)
                                    <td>
                                        @php $val = $get($row, $key); @endphp
                                        @if (in_array($key, ['image', 'photo', 'qr_image']))
                                            @if ($val)
                                                <img src="{{ asset('storage/' . $val) }}" class="h-10 w-10 rounded-lg object-cover" alt="">
                                            @else
                                                <span class="grid h-10 w-10 place-items-center rounded-lg bg-ink-100 text-xs text-ink-400 dark:bg-ink-800">—</span>
                                            @endif
                                        @elseif ($key === 'description')
                                            <span class="line-clamp-2 block max-w-[280px] text-xs text-ink-500">{{ strip_tags($val ?? '') }}</span>
                                        @elseif (in_array($key, ['rate', 'price', 'discount', 'making_charge']))
                                            <span class="font-mono text-[13px]">{{ money_in($val) }}</span>
                                        @elseif ($val instanceof \Carbon\Carbon)
                                            {{ $val->format('d M Y') }}
                                        @else
                                            <span class="{{ $loop->first ? 'font-medium' : '' }}">{{ $val ?: '—' }}</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td>
                                    @if (isset($row->status))
                                        <form method="POST" action="{{ route('admin.master.toggle', [$entity, $row->id]) }}">
                                            @csrf
                                            <button class="{{ $row->status ? 'badge-green' : 'badge-gray' }}">
                                                {{ $row->status ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="whitespace-nowrap text-right">
                                    <button @click="edit = !edit" class="btn-ghost btn-sm">Edit</button>
                                    <form method="POST" action="{{ route('admin.master.destroy', [$entity, $row->id]) }}" class="inline"
                                          onsubmit="return confirm('Delete this record?')">
                                        @csrf @method('DELETE')
                                        <button class="btn-ghost btn-sm text-rose-600">Delete</button>
                                    </form>

                                    {{-- inline edit drawer --}}
                                    <template x-teleport="body">
                                        <div x-show="edit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                            <div class="absolute inset-0 bg-ink-950/60 backdrop-blur-sm" @click="edit = false"></div>
                                            <div class="relative w-full max-w-lg rounded-2xl border border-ink-200 bg-white shadow-pop dark:border-ink-700 dark:bg-ink-900">
                                                <div class="card-head">
                                                    <h3 class="card-title">Edit {{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::singular($entity)) }}</h3>
                                                    <button @click="edit = false" class="btn-ghost btn-icon">&times;</button>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data"
                                                      action="{{ route('admin.master.update', [$entity, $row->id]) }}"
                                                      class="max-h-[70vh] overflow-y-auto p-5">
                                                    @csrf @method('PUT')
                                                    <div class="space-y-4">
                                                        @foreach ($fields as $f)
                                                            @include('admin.masters._field', ['f' => $f, 'value' => $row->{$f['name']} ?? null, 'options' => $options])
                                                        @endforeach
                                                        <label class="flex items-center gap-2 text-sm">
                                                            <input type="checkbox" name="status" value="1" @checked($row->status ?? true)
                                                                   class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                                                            Active
                                                        </label>
                                                    </div>
                                                    <div class="mt-5 flex gap-2">
                                                        <button class="btn-primary flex-1">Update</button>
                                                        <button type="button" @click="edit = false" class="btn-outline">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        @empty
                            <x-empty :cols="count($columns) + 2" message="No records yet" />
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>

    {{-- ---------------- ADD FORM ---------------- --}}
    <div class="card h-fit">
        <div class="card-head">
            <h3 class="card-title">Add {{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::singular($entity)) }}</h3>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.master.store', $entity) }}" class="card-pad space-y-4">
            @csrf
            @foreach ($fields as $f)
                @include('admin.masters._field', ['f' => $f, 'value' => null, 'options' => $options])
            @endforeach
            <button class="btn-primary w-full">Save</button>
        </form>
    </div>
</div>

@endsection
