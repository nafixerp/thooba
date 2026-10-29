@extends('layouts.app')
@section('title', $customer->exists ? 'Edit Customer' : 'New Customer')

@section('content')

<form method="POST" enctype="multipart/form-data"
      action="{{ $customer->exists ? route('admin.customers.update', $customer) : route('admin.customers.store') }}">
    @csrf
    @if ($customer->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        <div class="space-y-5 lg:col-span-2">
            <div class="card">
                <div class="card-head"><h3 class="card-title">Basic Details</h3></div>
                <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="label">Full Name *</label><input name="name" class="input" required value="{{ old('name', $customer->name) }}"></div>
                    <div><label class="label">Phone *</label><input name="phone" class="input" required value="{{ old('phone', $customer->phone) }}"></div>
                    <div><label class="label">Email</label><input type="email" name="email" class="input" value="{{ old('email', $customer->email) }}"></div>
                    <div><label class="label">Date of Birth</label><input type="date" name="dob" class="input" value="{{ old('dob', $customer->dob?->toDateString()) }}"></div>
                    <div>
                        <label class="label">Gender</label>
                        <select name="gender" class="input">
                            <option value="">—</option>
                            @foreach (['MALE','FEMALE','OTHER'] as $g)
                                <option value="{{ $g }}" @selected(old('gender', $customer->gender) === $g)>{{ ucfirst(strtolower($g)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Agent</label>
                        <select name="agent_id" class="input">
                            <option value="">— none —</option>
                            @foreach ($agents as $a)
                                <option value="{{ $a->id }}" @selected(old('agent_id', $customer->agent_id) == $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2"><label class="label">Address</label><textarea name="address" rows="2" class="input">{{ old('address', $customer->address) }}</textarea></div>
                    <div>
                        <label class="label">Country</label>
                        <select name="country_id" class="input">
                            <option value="">—</option>
                            @foreach ($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id', $customer->country_id) == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">State</label>
                        <select name="state_id" class="input">
                            <option value="">—</option>
                            @foreach ($states as $s)<option value="{{ $s->id }}" @selected(old('state_id', $customer->state_id) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">District</label>
                        <select name="district_id" class="input">
                            <option value="">—</option>
                            @foreach ($districts as $d)<option value="{{ $d->id }}" @selected(old('district_id', $customer->district_id) == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div><label class="label">Pincode</label><input name="pincode" class="input" value="{{ old('pincode', $customer->pincode) }}"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3 class="card-title">KYC &amp; Nominee</h3></div>
                <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><label class="label">Aadhaar</label><input name="aadhaar" class="input" value="{{ old('aadhaar', $customer->aadhaar) }}"></div>
                    <div><label class="label">PAN</label><input name="pan" class="input" value="{{ old('pan', $customer->pan) }}"></div>
                    <div>
                        <label class="label">KYC Status</label>
                        <select name="kyc_status" class="input">
                            @foreach (['PENDING','VERIFIED','REJECTED'] as $k)
                                <option value="{{ $k }}" @selected(old('kyc_status', $customer->kyc_status) === $k)>{{ ucfirst(strtolower($k)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="label">Join Date</label><input type="date" name="join_date" class="input" value="{{ old('join_date', $customer->join_date?->toDateString()) }}"></div>
                    <div><label class="label">Nominee Name</label><input name="nominee_name" class="input" value="{{ old('nominee_name', $customer->nominee_name) }}"></div>
                    <div><label class="label">Nominee Relation</label><input name="nominee_relation" class="input" value="{{ old('nominee_relation', $customer->nominee_relation) }}"></div>
                    <div><label class="label">Nominee Phone</label><input name="nominee_phone" class="input" value="{{ old('nominee_phone', $customer->nominee_phone) }}"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3 class="card-title">Bank Details</h3></div>
                <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label class="label">Bank Name</label><input name="bank_name" class="input" value="{{ old('bank_name', $customer->bank_name) }}"></div>
                    <div><label class="label">Account No</label><input name="account_no" class="input" value="{{ old('account_no', $customer->account_no) }}"></div>
                    <div><label class="label">IFSC</label><input name="ifsc" class="input" value="{{ old('ifsc', $customer->ifsc) }}"></div>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card">
                <div class="card-head"><h3 class="card-title">Photo &amp; Access</h3></div>
                <div class="card-pad space-y-4">
                    @if ($customer->photo)
                        <img src="{{ asset('storage/' . $customer->photo) }}" class="h-24 w-24 rounded-xl object-cover" alt="">
                    @endif
                    <div><label class="label">Photo</label><input type="file" name="photo" accept="image/*" class="input"></div>
                    <div>
                        <label class="label">App Password</label>
                        <input type="password" name="password" class="input" placeholder="{{ $customer->exists ? 'Leave blank to keep' : 'Defaults to phone number' }}">
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="status" value="1" @checked(old('status', $customer->status ?? true))
                               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                        Active
                    </label>
                    @if ($customer->exists && $customer->accode)
                        <div class="rounded-lg bg-ink-100 p-3 text-xs dark:bg-ink-800">
                            Ledger: <a href="{{ route('accounts.reports.general-ledger', ['accode' => $customer->accode]) }}"
                                       class="font-mono font-semibold text-brand-600 hover:underline">{{ $customer->accode }}</a>
                        </div>
                    @endif
                </div>
            </div>

            @unless ($customer->exists)
                {{-- Shown outright, not folded behind a checkbox.
                     It was hidden behind an Alpine x-show, which meant the
                     Scheme No and Book No fields were invisible on arrival -
                     and if the Alpine CDN is slow or blocked they never appear
                     at all. Leaving Scheme blank simply creates the customer
                     with no enrolment, so nothing needs to gate it.

                     Offered only when creating: enrolling from the edit screen
                     would quietly hand an existing customer a second scheme. --}}
                <div class="card">
                    <div class="card-head">
                        <h3 class="card-title">Start a Scheme</h3>
                        <span class="text-xs text-ink-500">optional</span>
                    </div>
                    <div class="card-pad space-y-4">
                        <div>
                            <label class="label">Scheme No</label>
                            <select name="scheme_id" class="input">
                                <option value="">— no scheme yet —</option>
                                @foreach ($schemes as $s)
                                    <option value="{{ $s->id }}" @selected(old('scheme_id') == $s->id)>
                                        {{ $s->code }} — {{ $s->name }} ({{ cur($s->amount) }} × {{ $s->total_installments }})
                                    </option>
                                @endforeach
                            </select>
                            @error('scheme_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            {{-- Proposed from the highest book in use; type over
                                 it when the book in hand says something else. --}}
                            <label class="label">Book No</label>
                            <input name="book_no" class="input" value="{{ old('book_no', $nextBookNo) }}">
                            @error('book_no')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="label">Installment Amount</label>
                            <input type="number" step="0.01" name="installment_amount" class="input"
                                   placeholder="scheme default" value="{{ old('installment_amount') }}">
                        </div>
                        <div>
                            <label class="label">Start Date</label>
                            <input type="date" name="scheme_start_date" class="input"
                                   value="{{ old('scheme_start_date', now()->toDateString()) }}">
                        </div>
                        <p class="text-xs text-ink-500">
                            Leave Scheme No blank to create the customer without a scheme.
                        </p>
                    </div>
                </div>
            @endunless

            <div class="flex gap-2">
                <button class="btn-primary flex-1">{{ $customer->exists ? 'Update' : 'Create Customer' }}</button>
                <a href="{{ route('admin.customers.index') }}" class="btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
