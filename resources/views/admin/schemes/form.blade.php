@extends('layouts.app')
@section('title', $scheme->exists ? 'Edit Scheme' : 'New Scheme')

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $scheme->exists ? route('admin.schemes.update', $scheme) : route('admin.schemes.store') }}">
    @csrf
    @if ($scheme->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-head"><h3 class="card-title">Scheme Details</h3></div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="label">Scheme Name *</label><input name="name" class="input" required value="{{ old('name', $scheme->name) }}"></div>
                <div><label class="label">Code *</label><input name="code" class="input font-mono" required value="{{ old('code', $scheme->code) }}"></div>
                <div>
                    <label class="label">Metal</label>
                    <select name="metal_id" class="input">
                        <option value="">—</option>
                        @foreach ($metals as $m)<option value="{{ $m->id }}" @selected(old('metal_id', $scheme->metal_id) == $m->id)>{{ $m->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Payment Interval *</label>
                    <select name="payment_interval" class="input" required>
                        @foreach (config('scheme.payment_intervals') as $i)
                            <option value="{{ $i }}" @selected(old('payment_interval', $scheme->payment_interval) === $i)>{{ ucfirst(strtolower($i)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Installment Amount *</label><input type="number" step="0.01" name="amount" class="input font-mono" required value="{{ old('amount', $scheme->amount) }}"></div>
                <div><label class="label">Total Installments *</label><input type="number" name="total_installments" class="input" required value="{{ old('total_installments', $scheme->total_installments) }}"></div>
                <div><label class="label">Min Amount</label><input type="number" step="0.01" name="min_amount" class="input font-mono" value="{{ old('min_amount', $scheme->min_amount ?? 0) }}"></div>
                <div><label class="label">Max Amount</label><input type="number" step="0.01" name="max_amount" class="input font-mono" value="{{ old('max_amount', $scheme->max_amount ?? 0) }}"></div>
                <div><label class="label">Duration (months)</label><input type="number" name="duration_months" class="input" value="{{ old('duration_months', $scheme->duration_months) }}"></div>
                <div><label class="label">GST %</label><input type="number" step="0.01" name="gst_percent" class="input" value="{{ old('gst_percent', $scheme->gst_percent ?? 3) }}"></div>
                <div>
                    <label class="label">Bonus Type *</label>
                    <select name="bonus_type" class="input" required>
                        @foreach (['NONE' => 'No bonus', 'PERCENT' => 'Percentage', 'AMOUNT' => 'Fixed amount', 'INSTALLMENT' => 'Free installments'] as $k => $v)
                            <option value="{{ $k }}" @selected(old('bonus_type', $scheme->bonus_type) === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Bonus Value</label><input type="number" step="0.01" name="bonus_value" class="input" value="{{ old('bonus_value', $scheme->bonus_value ?? 0) }}"></div>
                <div class="sm:col-span-2"><label class="label">Description</label><textarea name="description" rows="2" class="input">{{ old('description', $scheme->description) }}</textarea></div>
                <div class="sm:col-span-2"><label class="label">Terms</label><textarea name="terms" rows="4" class="input">{{ old('terms', $scheme->terms) }}</textarea></div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card">
                <div class="card-head"><h3 class="card-title">Options</h3></div>
                <div class="card-pad space-y-3">
                    @if ($scheme->image)<img src="{{ asset('storage/' . $scheme->image) }}" class="h-24 w-full rounded-lg object-cover" alt="">@endif
                    <div><label class="label">Image</label><input type="file" name="image" accept="image/*" class="input"></div>
                    @foreach ([
                        ['flexible_amount', 'Flexible amount', $scheme->flexible_amount],
                        ['gram_based', 'Convert amount to gram', $scheme->gram_based ?? true],
                        ['status', 'Active', $scheme->status ?? true],
                    ] as [$name, $label, $val])
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $val))
                                   class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary flex-1">{{ $scheme->exists ? 'Update' : 'Create Scheme' }}</button>
                <a href="{{ route('admin.schemes.index') }}" class="btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>
@endsection
