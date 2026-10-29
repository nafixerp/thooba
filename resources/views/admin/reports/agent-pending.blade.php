@extends('layouts.app')
@section('title', 'Agent Pending')
@section('subtitle', 'Collection and commission position per agent')

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Agent</th><th>Phone</th><th class="text-right">Customers</th>
                <th class="text-right">Collected</th><th class="text-right">Commission</th>
                <th class="text-right">Paid</th><th class="text-right">Pending</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td class="font-mono text-xs">{{ $r['agent']->agent_code }}</td>
                        <td><a href="{{ route('admin.agents.show', $r['agent']) }}" class="font-medium hover:text-brand-600 hover:underline">{{ $r['agent']->name }}</a></td>
                        <td>{{ $r['agent']->phone }}</td>
                        <td class="num">{{ $r['agent']->customers_count }}</td>
                        <td class="num font-semibold">{{ money_in($r['collected']) }}</td>
                        <td class="num">{{ money_in($r['earned']) }}</td>
                        <td class="num text-ink-500">{{ money_in($r['paid']) }}</td>
                        <td class="num font-bold {{ $r['pending'] > 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ money_in($r['pending']) }}</td>
                    </tr>
                @empty
                    <x-empty cols="8" message="No agents" />
                @endforelse
            </tbody>
            @if (count($rows))
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right">TOTAL</td>
                        <td class="num">{{ money_in(collect($rows)->sum('collected')) }}</td>
                        <td class="num">{{ money_in(collect($rows)->sum('earned')) }}</td>
                        <td class="num">{{ money_in(collect($rows)->sum('paid')) }}</td>
                        <td class="num">{{ money_in(collect($rows)->sum('pending')) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
