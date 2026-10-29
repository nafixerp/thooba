@extends('layouts.app')
@section('title', $ledger->exists ? 'Edit Ledger' : 'New Ledger')
@section('subtitle', 'accountm record — this accode is what daybook posts against')

@section('content')

<form method="POST"
      action="{{ $ledger->exists ? route('accounts.ledgers.update', $ledger->accode) : route('accounts.ledgers.store') }}">
    @csrf
    @if ($ledger->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        <div class="card lg:col-span-2">
            <div class="card-head"><h3 class="card-title">Ledger Details</h3></div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Ledger Code (accode) *</label>
                    <input name="accode" class="input font-mono" required maxlength="15"
                           value="{{ old('accode', $ledger->accode) }}" {{ $ledger->exists ? 'readonly' : '' }}>
                </div>
                <div>
                    <label class="label">Ledger Name *</label>
                    <input name="name" class="input" required value="{{ old('name', $ledger->name) }}">
                </div>
                <div>
                    <label class="label">Alias / Short Name</label>
                    <input name="alias" class="input" value="{{ old('alias', $ledger->alias) }}">
                </div>
                <div>
                    <label class="label">Account Group (grcode) *</label>
                    <select name="grcode" class="input" required>
                        @foreach ($groups as $g)
                            <option value="{{ $g->grcode }}" @selected(old('grcode', $ledger->grcode) === $g->grcode)>
                                {{ $g->base?->name }} › {{ $g->name }} ({{ $g->bshead }})
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">bshead is taken from the group automatically.</p>
                </div>
                <div>
                    <label class="label">Ledger Type *</label>
                    <select name="actype" class="input" required>
                        @foreach (['GENERAL','CASH','BANK','CUSTOMER','AGENT','SUPPLIER','INCOME','EXPENSE','CAPITAL','DUTY','STOCK'] as $t)
                            <option value="{{ $t }}" @selected(old('actype', $ledger->actype) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Opening Balance</label>
                        <input type="number" step="0.01" name="opening_balance" class="input"
                               value="{{ old('opening_balance', $ledger->opening_balance ?? 0) }}">
                    </div>
                    <div>
                        <label class="label">Dr / Cr</label>
                        <select name="opening_drcr" class="input">
                            <option value="D" @selected(old('opening_drcr', $ledger->opening_drcr) === 'D')>Debit</option>
                            <option value="C" @selected(old('opening_drcr', $ledger->opening_drcr) === 'C')>Credit</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">Opening Date</label>
                    <input type="date" name="opening_date" class="input"
                           value="{{ old('opening_date', $ledger->opening_date?->toDateString()) }}">
                </div>
                <div>
                    <label class="label">Credit Limit</label>
                    <input type="number" step="0.01" name="credit_limit" class="input"
                           value="{{ old('credit_limit', $ledger->credit_limit ?? 0) }}">
                </div>

                {{-- Where this ledger lands in the statements. A P&L ledger
                     closes into one of the trading sections or into the P&L A/c
                     itself; a balance sheet ledger prints under a schedule. --}}
                <div>
                    <label class="label">Statement section</label>
                    <select name="tplpos" class="input">
                        @foreach (config('scheme.tpl_sections') as $pos => $label)
                            <option value="{{ $pos }}" @selected((int) old('tplpos', $ledger->tplpos ?? 0) === (int) $pos)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">
                        Only applies to Profit &amp; Loss ledgers. Direct income and direct cost belong in the
                        Trading A/c so they close into gross profit; everything else belongs in the Profit &amp; Loss A/c.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="label">Balance Sheet schedule</label>
                        <input name="schedule_group" class="input" maxlength="60"
                               value="{{ old('schedule_group', $ledger->schedule_group) }}"
                               placeholder="Defaults to the group name">
                    </div>
                    <div>
                        <label class="label">Position</label>
                        <input type="number" name="schedule_pos" class="input"
                               value="{{ old('schedule_pos', $ledger->schedule_pos ?? 0) }}">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="print_in_reports" value="1" class="checkbox"
                           @checked(old('print_in_reports', $ledger->print_in_reports ?? true))>
                    Include in the printed statements
                </label>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card">
                <div class="card-head"><h3 class="card-title">Party Details</h3></div>
                <div class="card-pad space-y-4">
                    <div>
                        <label class="label">Phone</label>
                        <input name="phone" class="input" value="{{ old('phone', $ledger->phone) }}">
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input type="email" name="email" class="input" value="{{ old('email', $ledger->email) }}">
                    </div>
                    <div>
                        <label class="label">Address</label>
                        <textarea name="address" class="input" rows="2">{{ old('address', $ledger->address) }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">GSTIN</label>
                            <input name="gstin" class="input" value="{{ old('gstin', $ledger->gstin) }}">
                        </div>
                        <div>
                            <label class="label">PAN</label>
                            <input name="pan" class="input" value="{{ old('pan', $ledger->pan) }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Bank A/c No</label>
                            <input name="bank_account_no" class="input" value="{{ old('bank_account_no', $ledger->bank_account_no) }}">
                        </div>
                        <div>
                            <label class="label">IFSC</label>
                            <input name="bank_ifsc" class="input" value="{{ old('bank_ifsc', $ledger->bank_ifsc) }}">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="status" value="1" @checked(old('status', $ledger->status ?? true))
                               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500 dark:border-ink-700 dark:bg-ink-900">
                        Active
                    </label>
                </div>
            </div>

            <div class="flex gap-2">
                <button class="btn-primary flex-1">{{ $ledger->exists ? 'Update Ledger' : 'Create Ledger' }}</button>
                <a href="{{ route('accounts.ledgers') }}" class="btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
