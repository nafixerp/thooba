@extends('layouts.app')
@section('title', 'Account Groups — accountg')
@section('subtitle', 'Level 2 of the chart of accounts')

@section('content')

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

    <div class="lg:col-span-2">
        <x-filters :action="route('accounts.groups')">
            <div class="min-w-[180px]">
                <label class="label">Group Base</label>
                <select name="gbscode" class="input" onchange="this.form.submit()">
                    <option value="">All bases</option>
                    @foreach ($bases as $b)
                        <option value="{{ $b->gbscode }}" @selected(request('gbscode') === $b->gbscode)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[180px] flex-1">
                <label class="label">Search</label>
                <input name="q" value="{{ request('q') }}" class="input" placeholder="Group name">
            </div>
            <button class="btn-primary">Filter</button>
        </x-filters>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th><th>Group</th><th>Base</th><th>Parent</th>
                            <th>Head</th><th>Type</th><th class="text-right">Ledgers</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $g)
                            <tr>
                                <td class="font-mono text-xs font-semibold">{{ $g->grcode }}</td>
                                <td class="font-medium">{{ $g->name }}</td>
                                <td class="text-ink-600 dark:text-ink-400">{{ $g->base?->name }}</td>
                                <td class="text-xs text-ink-500">{{ $g->parent?->name ?: '—' }}</td>
                                <td><span class="badge-gray">{{ $g->bshead }}</span></td>
                                <td>
                                    <span class="{{ $g->actype === 'CONTROL' ? 'badge-brand' : 'badge-gray' }}">{{ $g->actype }}</span>
                                </td>
                                <td class="num">{{ $g->ledgers_count }}</td>
                                <td class="text-right">
                                    @if ($g->actype !== 'CONTROL' && $g->ledgers_count === 0)
                                        <form method="POST" action="{{ route('accounts.groups.destroy', $g->grcode) }}"
                                              onsubmit="return confirm('Delete this group?')">
                                            @csrf @method('DELETE')
                                            <button class="btn-ghost btn-sm text-rose-600">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty cols="8" message="No groups found" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($groups->hasPages())
                <div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $groups->links() }}</div>
            @endif
        </div>
    </div>

    <div class="card h-fit">
        <div class="card-head"><h3 class="card-title">Add Account Group</h3></div>
        <form method="POST" action="{{ route('accounts.groups.store') }}" class="card-pad space-y-4">
            @csrf
            <div>
                <label class="label">Group Code *</label>
                <input name="grcode" class="input" required maxlength="10" placeholder="G4030">
            </div>
            <div>
                <label class="label">Group Name *</label>
                <input name="name" class="input" required placeholder="Travel Expenses">
            </div>
            <div>
                <label class="label">Group Base *</label>
                <select name="gbscode" class="input" required>
                    @foreach ($bases as $b)
                        <option value="{{ $b->gbscode }}">{{ $b->gbscode }} — {{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Parent Group</label>
                <select name="parent_grcode" class="input">
                    <option value="">None (top level)</option>
                    @foreach ($parents as $p)
                        <option value="{{ $p->grcode }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Type *</label>
                <select name="actype" class="input" required>
                    <option value="NORMAL">Normal (editable)</option>
                    <option value="CONTROL">Control (system)</option>
                </select>
            </div>
            <div>
                <label class="label">Sort Order</label>
                <input type="number" name="sortorder" class="input" value="50">
            </div>
            <button class="btn-primary w-full">Save Group</button>
        </form>
    </div>
</div>

@endsection
