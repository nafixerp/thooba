@extends('layouts.app')
@section('title', 'Company Settings')

@php
    $isSuperAdmin = auth()->user()?->role === 'SUPERADMIN';
    $prefixFields = [
        'customer_code_prefix' => ['Customer Code Prefix', 'CUS'],
        'scheme_account_prefix' => ['Scheme Account Prefix', 'SCH'],
        'receipt_code_prefix' => ['Receipt Code Prefix', 'RCT'],
        'voucher_prefix_receipt' => ['Receipt Voucher Prefix', 'RCP'],
        'voucher_prefix_payment' => ['Payment Voucher Prefix', 'PMT'],
        'voucher_prefix_sales' => ['Sales Voucher Prefix', 'SAL'],
        'voucher_prefix_journal' => ['Journal Voucher Prefix', 'JV'],
    ];
@endphp

@section('content')
<form method="POST" action="{{ route('admin.settings.save') }}" enctype="multipart/form-data">
    @csrf
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

        <div class="card">
            <div class="card-head"><h3 class="card-title">Company Details</h3></div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ([
                    'company_name' => 'Company Name',
                    'company_tagline' => 'Tagline',
                    'company_phone' => 'Phone',
                    'company_email' => 'Email',
                    'company_gstin' => 'GSTIN',
                ] as $k => $label)
                    <div>
                        <label class="label">{{ $label }}</label>
                        <input name="{{ $k }}" class="input" value="{{ $settings[$k] ?? '' }}">
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label class="label">Address</label>
                    <textarea name="company_address" rows="2" class="input">{{ $settings['company_address'] ?? '' }}</textarea>
                </div>

                {{-- Direct UPI collection: the app opens Google Pay with a
                     upi://pay link straight to this id, no aggregator. --}}
                @php $upi = app(\App\Services\UpiService::class); @endphp
                <div>
                    <label class="label">UPI ID for collections</label>
                    <input name="upi_id" class="input" value="{{ $settings['upi_id'] ?? '' }}"
                           placeholder="shopname@bank">
                    <p class="form-hint">
                        Blank falls back to the first active bank's UPI id.
                        @if ($upi->enabled())
                            <span class="text-emerald-700 dark:text-emerald-400">
                                In use: {{ $upi->vpa() }}
                            </span>
                        @else
                            <span class="text-rose-700 dark:text-rose-400">
                                {{ $upi->disabledReason() }}
                            </span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="label">UPI payee name</label>
                    <input name="upi_payee_name" class="input" value="{{ $settings['upi_payee_name'] ?? '' }}"
                           placeholder="{{ $settings['company_name'] ?? 'Shop name' }}">
                    <p class="form-hint">Shown in the customer's UPI app.</p>
                </div>

                <div class="sm:col-span-2 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-relaxed dark:bg-amber-500/10">
                    A direct UPI payment has no gateway behind it, so nothing can prove the money
                    arrived — the result a UPI app returns is not something the shop can trust.
                    These arrive under <a href="{{ route('admin.payments.pending') }}" class="font-medium underline">Payments → Pending</a>
                    with the customer's reference; approve one only after you see it on the bank
                    statement. Nothing reaches the accounts until you do.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3 class="card-title">Scheme &amp; Points</h3></div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">GST mode</label>
                    @php $gstMode = $settings['gst_mode'] ?? config('scheme.gst_mode'); @endphp
                    <select name="gst_mode" class="input">
                        @foreach (config('scheme.gst_modes') as $k => $label)
                            <option value="{{ $k }}" @selected($gstMode === $k)>{{ $k }} — {{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint">
                        Decides what a customer actually hands over for an installment, and how the
                        receipt splits. Whichever is chosen, money received always equals the scheme
                        credit plus GST, which is what the receipt voucher posts.
                    </p>
                    <div class="mt-2 rounded-lg bg-ink-50 px-3 py-2.5 text-xs leading-relaxed dark:bg-ink-950/50">
                        @php
                            $pct = (float) ($settings['gst_percent'] ?? config('scheme.gst_percent'));
                            $demo = 5000.0;
                            $exTax = round($demo * $pct / 100, 2);
                            $inBase = $pct > 0 ? round($demo * 100 / (100 + $pct), 2) : $demo;
                        @endphp
                        <span class="font-medium">On a {{ money_in($demo) }} installment at {{ $pct }}%:</span><br>
                        <span class="text-ink-500">Added on top</span> — customer pays
                        {{ money_in($demo + $exTax) }} ({{ money_in($demo) }} + {{ money_in($exTax) }} GST)<br>
                        <span class="text-ink-500">Already included</span> — customer pays
                        {{ money_in($demo) }} ({{ money_in($inBase) }} + {{ money_in($demo - $inBase) }} GST)<br>
                        <span class="text-ink-500">Not charged</span> — customer pays {{ money_in($demo) }}
                    </div>
                </div>
                <div>
                    <label class="label">Default GST %</label>
                    <input type="number" step="0.01" name="gst_percent" class="input" value="{{ $settings['gst_percent'] ?? 3 }}">
                    <p class="form-hint">A scheme with its own GST % overrides this.</p>
                </div>
                <div>
                    <label class="label">Referral Points</label>
                    <input type="number" step="0.01" name="referral_points" class="input" value="{{ $settings['referral_points'] ?? 100 }}">
                </div>
                <div>
                    <label class="label">Point Value (Rs per point)</label>
                    <input type="number" step="0.01" name="point_value" class="input" value="{{ $settings['point_value'] ?? 1 }}">
                </div>
                <div>
                    <label class="label">Due reminder days</label>
                    <input type="number" min="0" max="30" name="due_reminder_days" class="input" value="{{ $settings['due_reminder_days'] ?? 3 }}">
                    <p class="form-hint">Create an in-app reminder this many days before each unpaid installment.</p>
                </div>
            </div>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-head">
                <h3 class="card-title">Code Prefixes</h3>
                <span class="badge-gray">New records only</span>
            </div>
            <div class="card-pad grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($prefixFields as $key => [$label, $default])
                    <div>
                        <label class="label">{{ $label }}</label>
                        <input name="{{ $key }}" class="input uppercase" value="{{ $settings[$key] ?? $default }}" maxlength="20">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-head"><h3 class="card-title">About Us</h3></div>
            <div class="card-pad space-y-4">
                <div><label class="label">Title</label><input name="about_title" class="input" value="{{ $about->title }}"></div>
                <div><label class="label">Description</label><textarea name="about_description" rows="5" class="input">{{ $about->description }}</textarea></div>
                @if ($about->image)<img src="{{ asset('storage/' . $about->image) }}" class="h-24 rounded-lg object-cover" alt="">@endif
                <div><label class="label">Image</label><input type="file" name="about_image" accept="image/*" class="input"></div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <button class="btn-primary">Save Settings</button>
        </div>
    </div>
</form>

@php
    $apkPath = $settings['android_apk_path'] ?? 'releases/scheme-erp.apk';
    $apkAvailable = \Illuminate\Support\Facades\Storage::disk('public')->exists($apkPath);
@endphp
<div class="card mt-5">
    <div class="card-head">
        <h3 class="card-title">Android App</h3>
        @if ($apkAvailable)
            <span class="badge-green">Ready to download</span>
        @else
            <span class="badge-gray">No APK uploaded</span>
        @endif
    </div>
    <div class="card-pad grid grid-cols-1 gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.settings.android-app') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="label">APK file</label>
                <input type="file" name="apk" accept=".apk,application/vnd.android.package-archive" class="input" required>
                @error('apk')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                <p class="form-hint">Maximum 200 MB. Uploading replaces the current installer.</p>
            </div>
            <div>
                <label class="label">Version</label>
                <input name="apk_version" class="input" maxlength="30"
                       value="{{ old('apk_version', $settings['android_apk_version'] ?? '') }}"
                       placeholder="1.0.0">
            </div>
            <button class="btn-primary">Upload Android App</button>
        </form>

        <div class="rounded-xl border border-ink-200 p-4 dark:border-ink-800">
            <div class="font-semibold text-ink-900 dark:text-white">Public download</div>
            @if ($apkAvailable)
                <p class="mt-2 text-sm text-ink-500">
                    Version {{ $settings['android_apk_version'] ?? 'latest' }}
                    @if (!empty($settings['android_apk_size']))
                        · {{ number_format(((int) $settings['android_apk_size']) / 1048576, 1) }} MB
                    @endif
                </p>
                <a href="{{ route('app.download') }}" class="btn-outline mt-4 inline-flex">Download APK</a>
                <div class="mt-3 break-all rounded-lg bg-ink-50 p-3 font-mono text-xs dark:bg-ink-950/50">
                    {{ route('app.download') }}
                </div>
            @else
                <p class="mt-2 text-sm text-ink-500">Upload the release APK to activate the permanent download link.</p>
            @endif
        </div>
    </div>
</div>

<div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="card">
        <div class="card-head"><h3 class="card-title">Administration</h3></div>
        <div class="card-pad space-y-4">
            <div class="rounded-lg border border-ink-200 p-4 dark:border-ink-800">
                <div class="font-semibold text-ink-900 dark:text-white">Users &amp; Roles</div>
                <p class="mt-1 text-sm text-ink-500">Create users and assign Super Admin, Admin, Accountant, or Staff role.</p>
                <a href="{{ route('admin.master.index', 'users') }}" class="btn-outline mt-3 inline-flex">Open Users &amp; Roles</a>
            </div>

            @unless ($isSuperAdmin)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                    SQL run and reset options are visible only for Super Admin.
                </div>
            @endunless
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3 class="card-title">Accounting Control Ledgers</h3></div>
        <div class="card-pad">
            <p class="mb-4 text-sm text-ink-500">
                These are set in <span class="kbd">.env</span> / <span class="kbd">config/scheme.php</span> and drive all auto posting.
            </p>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Purpose</th><th>Config key</th><th>accode</th><th>Ledger name</th></tr></thead>
                    <tbody>
                        @foreach (config('scheme.accounts') as $key => $accode)
                            @php $acc = \App\Models\Accountm::find($accode); @endphp
                            <tr>
                                <td class="font-medium">{{ \Illuminate\Support\Str::headline($key) }}</td>
                                <td class="font-mono text-xs text-ink-500">scheme.accounts.{{ $key }}</td>
                                <td class="font-mono text-xs font-semibold">{{ $accode }}</td>
                                <td>
                                    @if ($acc)
                                        <a href="{{ route('accounts.reports.general-ledger', ['accode' => $accode]) }}" class="text-brand-600 hover:underline">{{ $acc->name }}</a>
                                    @else
                                        <span class="badge-rose">missing - run the seeder</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($isSuperAdmin)
    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="card">
            <div class="card-head"><h3 class="card-title">Run SQL Command</h3></div>
            <form method="POST" action="{{ route('admin.settings.sql') }}" class="card-pad space-y-4">
                @csrf
                <div>
                    <label class="label">SQL</label>
                    <textarea name="sql" rows="7" class="input font-mono text-sm" placeholder="SELECT * FROM customers LIMIT 10">{{ old('sql') }}</textarea>
                    @error('sql')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Admin Password</label>
                        <input type="password" name="password" class="input" autocomplete="current-password">
                    </div>
                    <div>
                        <label class="label">Confirm Phrase</label>
                        <input name="confirm_phrase" class="input" placeholder="RUN SQL">
                    </div>
                </div>
                <button class="btn-primary">Run SQL</button>
            </form>

            @if ($sqlResult)
                <div class="border-t border-ink-200 p-5 dark:border-ink-800">
                    @if (($sqlResult['type'] ?? '') === 'select')
                        <div class="mb-3 text-sm text-ink-500">Showing {{ count($sqlResult['rows']) }} of {{ $sqlResult['count'] }} rows.</div>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        @foreach ($sqlResult['columns'] as $column)
                                            <th>{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($sqlResult['rows'] as $row)
                                        <tr>
                                            @foreach ($sqlResult['columns'] as $column)
                                                <td class="font-mono text-xs">{{ $row[$column] ?? '' }}</td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td class="p-4 text-sm text-ink-500">No rows returned.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="badge-green">{{ $sqlResult['affected'] ?? 0 }} rows affected</div>
                    @endif
                </div>
            @endif
        </div>

        <div class="card border-rose-200 dark:border-rose-900/60">
            <div class="card-head"><h3 class="card-title text-rose-700 dark:text-rose-300">Clear All Data Reset</h3></div>
            <form method="POST" action="{{ route('admin.settings.reset-data') }}" class="card-pad space-y-4"
                  onsubmit="return confirm('Clear all business entries? This cannot be undone.')">
                @csrf
                <div class="space-y-2 text-sm text-ink-500">
                    <p>
                        <span class="font-semibold">Cleared:</span>
                        customers, agents, scheme enrolments, installments, payments, gateway
                        orders, bank statement lines, points, the daybook and all ledger
                        balances, login OTPs and app tokens. Every user except you.
                    </p>
                    <p>
                        <span class="font-semibold">Kept:</span>
                        your scheme master, banks, metal rates, jewellery catalogue, banners,
                        offers, terms, company settings and branches. The chart of accounts is
                        rebuilt empty.
                    </p>
                    <p>Take a database backup first. This cannot be undone.</p>
                </div>
                @error('reset')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                <div>
                    <label class="label">Admin Password</label>
                    <input type="password" name="password" class="input" autocomplete="current-password">
                </div>
                <div>
                    <label class="label">Type RESET DATA</label>
                    <input name="confirm_phrase" class="input" placeholder="RESET DATA">
                </div>
                <button class="btn-danger w-full">Clear All Data</button>
            </form>
        </div>
    </div>
@endif
@endsection
