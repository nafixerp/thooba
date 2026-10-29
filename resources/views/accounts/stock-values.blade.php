@extends('layouts.app')
@section('title', 'Closing Stock')
@section('subtitle', 'The valued stock figure the Trading A/c and the Balance Sheet both use')

@section('content')

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

    {{-- ---------- entry ---------- --}}
    <div class="lg:col-span-1">
        <div class="card card-pad">
            <h3 class="card-title mb-4">Enter a valuation</h3>

            <form method="POST" action="{{ route('accounts.stock-values.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label">As on</label>
                    <input type="date" name="as_on" value="{{ old('as_on', now()->toDateString()) }}" class="input" required>
                </div>
                <div>
                    <label class="label">Stock value</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="input" required>
                </div>
                <div>
                    <label class="label">Weight (gram)</label>
                    <input type="number" step="0.001" min="0" name="gram" value="{{ old('gram') }}" class="input">
                </div>
                <div>
                    <label class="label">Note</label>
                    <input type="text" name="note" value="{{ old('note') }}" class="input" maxlength="190"
                           placeholder="Physical count, 31 March">
                </div>
                <button class="btn-primary w-full">Save</button>
            </form>

            <p class="mt-4 text-xs leading-relaxed text-ink-500">
                One valuation per date; saving the same date again replaces it. Reports take the most
                recent valuation on or before their end date. With none entered the stock ledger's own
                balance is used, and the statements read exactly as they did before.
            </p>

            @if ($ledger)
                <div class="mt-4 rounded-lg bg-ink-50 px-3 py-2.5 text-xs dark:bg-ink-950/50">
                    <span class="text-ink-500">Stock ledger</span>
                    <span class="ml-1 font-medium">{{ $ledger->name }}</span>
                    <span class="num ml-1">{{ drcr_label($ledger->balance) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ---------- history ---------- --}}
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Valuations</h3>
                <span class="badge-gray">{{ $rows->total() }}</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>As on</th>
                            <th class="text-right">Value</th>
                            <th class="text-right">Gram</th>
                            <th>Note</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td class="font-medium">{{ $r->as_on->format('d M Y') }}</td>
                                <td class="num text-right">{{ money_in($r->amount) }}</td>
                                <td class="num text-right">{{ number_format((float) $r->gram, 3) }}</td>
                                <td class="text-ink-500">{{ $r->note }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('accounts.stock-values.destroy', $r->id) }}"
                                          onsubmit="return confirm('Remove this valuation?')">
                                        @csrf @method('DELETE')
                                        <button class="text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-empty :cols="5"
                                     message="No valuation entered yet — the Trading A/c carries the stock ledger balance straight through." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="px-5 py-3">{{ $rows->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection
