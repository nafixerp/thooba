@extends('layouts.app')
@section('title', 'Pending Commission')
@section('subtitle', 'Commission earned but not yet paid out')

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Agent</th><th>Phone</th><th class="text-right">Collected</th>
                <th class="text-right">Rate %</th><th class="text-right">Earned</th><th class="text-right">Paid</th><th class="text-right">Pending</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs">{{ $r['agent']->agent_code }}</td>
                        <td><a href="{{ route('admin.agents.show', $r['agent']) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r['agent']->name }}</a></td>
                        <td>{{ $r['agent']->phone }}</td>
                        <td class="num">{{ money_in($r['collected']) }}</td>
                        <td class="num">{{ number_format($r['agent']->commission_percent, 2) }}</td>
                        <td class="num">{{ money_in($r['earned']) }}</td>
                        <td class="num text-ink-500">{{ money_in($r['paid']) }}</td>
                        <td class="num font-bold text-amber-600 dark:text-amber-400">{{ money_in($r['pending']) }}</td>
                        <td class="text-right"><a href="{{ route('admin.agents.payouts') }}" class="btn-primary btn-sm">Pay</a></td>
                    </tr>
                @empty
                    <x-empty cols="9" message="All commissions are settled 🎉" />
                @endforelse
            </tbody>
            @if (count($rows))
                <tfoot>
                    <tr>
                        <td colspan="7" class="text-right">TOTAL PENDING</td>
                        <td class="num">{{ money_in(collect($rows)->sum('pending')) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
