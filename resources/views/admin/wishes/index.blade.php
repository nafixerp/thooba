@extends('layouts.app')
@section('title', 'Customer Wishes')

@section('content')
<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <div class="card lg:col-span-1">
        <div class="card-head"><h3 class="card-title">Send a Wish</h3></div>
        <form method="POST" action="{{ route('admin.wishes.store') }}" class="card-pad space-y-4">
            @csrf
            <div>
                <label class="label">Customer</label>
                <select name="customer_id" class="input" required>
                    <option value="">Select customer</option>
                    <option value="all" @selected(old('customer_id') === 'all')>All active customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                            {{ $customer->name }} ({{ $customer->phone }})
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Wish / Occasion</label>
                <input name="occasion" class="input" required maxlength="120" value="{{ old('occasion') }}" placeholder="Birthday Wishes">
                @error('occasion')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Message</label>
                <textarea name="message" class="input min-h-32" required maxlength="1000" placeholder="Wishing you a wonderful day...">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-ink-500">The wish will appear in the customer app under your jewellery name.</p>
            <button class="btn-primary w-full">Send Wish</button>
        </form>
    </div>

    <div class="card lg:col-span-2">
        <div class="card-head"><h3 class="card-title">Recently Sent Wishes</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Customer</th><th>Wish</th><th>Message</th><th>Sent</th></tr></thead>
                <tbody>
                    @forelse ($wishes as $wish)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $wish->notifiable->name ?? 'Customer' }}</div>
                                <div class="text-xs text-ink-500">{{ $wish->notifiable->phone ?? '' }}</div>
                            </td>
                            <td>{{ $wish->title }}</td>
                            <td class="max-w-sm whitespace-normal">{{ $wish->message }}</td>
                            <td class="whitespace-nowrap">{{ optional($wish->sent_at)->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <x-empty cols="4" message="No wishes sent yet" />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($wishes->hasPages())<div class="border-t border-ink-200 px-5 py-3 dark:border-ink-800">{{ $wishes->links() }}</div>@endif
    </div>
</div>
@endsection
