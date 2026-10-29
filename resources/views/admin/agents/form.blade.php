@extends('layouts.app')
@section('title', $agent->exists ? 'Edit Agent' : 'New Agent')

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $agent->exists ? route('admin.agents.update', $agent) : route('admin.agents.store') }}">
    @csrf
    @if ($agent->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-head"><h3 class="card-title">Agent Details</h3></div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="label">Name *</label><input name="name" class="input" required value="{{ old('name', $agent->name) }}"></div>
                <div><label class="label">Phone *</label><input name="phone" class="input" required value="{{ old('phone', $agent->phone) }}"></div>
                <div><label class="label">Email</label><input type="email" name="email" class="input" value="{{ old('email', $agent->email) }}"></div>
                <div><label class="label">Commission %</label><input type="number" step="0.01" name="commission_percent" class="input" value="{{ old('commission_percent', $agent->commission_percent ?? 0) }}"></div>
                <div><label class="label">Aadhaar</label><input name="aadhaar" class="input" value="{{ old('aadhaar', $agent->aadhaar) }}"></div>
                <div><label class="label">PAN</label><input name="pan" class="input" value="{{ old('pan', $agent->pan) }}"></div>
                <div class="sm:col-span-2"><label class="label">Address</label><textarea name="address" rows="2" class="input">{{ old('address', $agent->address) }}</textarea></div>
                <div><label class="label">Bank Name</label><input name="bank_name" class="input" value="{{ old('bank_name', $agent->bank_name) }}"></div>
                <div><label class="label">Account No</label><input name="account_no" class="input" value="{{ old('account_no', $agent->account_no) }}"></div>
                <div><label class="label">IFSC</label><input name="ifsc" class="input" value="{{ old('ifsc', $agent->ifsc) }}"></div>
                <div><label class="label">Join Date</label><input type="date" name="join_date" class="input" value="{{ old('join_date', $agent->join_date?->toDateString()) }}"></div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card">
                <div class="card-head"><h3 class="card-title">Photo &amp; Access</h3></div>
                <div class="card-pad space-y-4">
                    @if ($agent->image)<img src="{{ asset('storage/' . $agent->image) }}" class="h-24 w-24 rounded-xl object-cover" alt="">@endif
                    <div><label class="label">Photo</label><input type="file" name="image" accept="image/*" class="input"></div>
                    <div>
                        <label class="label">{{ $agent->exists ? 'New login password' : 'Login password' }}</label>
                        <input type="password" name="password" class="input" minlength="6"
                               placeholder="{{ $agent->exists ? 'Leave blank to keep current' : 'Defaults to mobile number' }}">
                    </div>
                    <div>
                        <label class="label">Confirm password</label>
                        <input type="password" name="password_confirmation" class="input" minlength="6">
                        @error('password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="must_change_password" value="1"
                               @checked(old('must_change_password', ! $agent->exists))
                               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                        Ask for a new password after next login
                    </label>
                    <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                        Leave the password blank when creating to use the registered mobile
                        number once. On edit, enter a new password here to reset app access.
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="status" value="1" @checked(old('status', $agent->status ?? true))
                               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                        Active
                    </label>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary flex-1">{{ $agent->exists ? 'Update' : 'Create Agent' }}</button>
                <a href="{{ route('admin.agents.index') }}" class="btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>
@endsection
