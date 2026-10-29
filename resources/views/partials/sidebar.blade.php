@php
    $company = \App\Models\Setting::get('company_name', 'Scheme ERP');
    $tagline = \App\Models\Setting::get('company_tagline', 'Gold & Diamonds');

    $nav = [
        ['type' => 'link', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'grid'],

        ['type' => 'section', 'label' => 'Scheme'],
        ['type' => 'group', 'label' => 'Customers', 'icon' => 'users', 'match' => ['admin.customers.*'], 'items' => [
            ['label' => 'All Customers', 'route' => 'admin.customers.index'],
            ['label' => 'Add Customer', 'route' => 'admin.customers.create'],
        ]],
        ['type' => 'group', 'label' => 'Agents', 'icon' => 'badge', 'match' => ['admin.agents.*'], 'items' => [
            ['label' => 'All Agents', 'route' => 'admin.agents.index'],
            ['label' => 'Add Agent', 'route' => 'admin.agents.create'],
            ['label' => 'Commission Payouts', 'route' => 'admin.agents.payouts'],
            ['label' => 'Pending Commission', 'route' => 'admin.agents.pending-commission'],
        ]],
        ['type' => 'group', 'label' => 'Schemes', 'icon' => 'layers', 'match' => ['admin.schemes.*'], 'items' => [
            ['label' => 'Scheme Master', 'route' => 'admin.schemes.index'],
            ['label' => 'Add Scheme', 'route' => 'admin.schemes.create'],
            ['label' => 'Enrollments', 'route' => 'admin.schemes.enrollments'],
            ['label' => 'Matured Schemes', 'route' => 'admin.schemes.matured'],
            ['label' => 'Expiring Installments', 'route' => 'admin.schemes.expiring'],
        ]],
        ['type' => 'group', 'label' => 'Payments', 'icon' => 'wallet', 'badge' => \App\Models\Payment::where('status', 'PENDING')->count(), 'match' => ['admin.payments.*'], 'items' => [
            ['label' => 'All Payments', 'route' => 'admin.payments.index'],
            ['label' => 'Pending Approval', 'route' => 'admin.payments.pending'],
            ['label' => 'New Payment', 'route' => 'admin.payments.create'],
        ]],
        ['type' => 'group', 'label' => 'Points & Referral', 'icon' => 'star', 'match' => ['admin.points.*'], 'items' => [
            ['label' => 'Customer Points', 'route' => 'admin.points.index'],
            ['label' => 'Redeem Report', 'route' => 'admin.points.redeem-report'],
            ['label' => 'Referrals', 'route' => 'admin.points.referrals'],
            ['label' => 'Privilege Card Setup', 'route' => 'admin.master.index', 'param' => 'privilege-tiers'],
        ]],
        ['type' => 'link', 'label' => 'Customer Wishes', 'route' => 'admin.wishes.index', 'icon' => 'gift'],
        ['type' => 'group', 'label' => 'Operations', 'icon' => 'tool', 'matchParam' => ['branches', 'commission-rules', 'roles', 'app-versions'], 'items' => [
            ['label' => 'Branches', 'route' => 'admin.master.index', 'param' => 'branches'],
            ['label' => 'Commission Rules', 'route' => 'admin.master.index', 'param' => 'commission-rules'],
            ['label' => 'Roles', 'route' => 'admin.master.index', 'param' => 'roles'],
            ['label' => 'App Versions', 'route' => 'admin.master.index', 'param' => 'app-versions'],
        ]],

        ['type' => 'section', 'label' => 'Accounts'],
        ['type' => 'group', 'label' => 'Chart of Accounts', 'icon' => 'book', 'match' => ['accounts.bases', 'accounts.groups*', 'accounts.ledgers*'], 'items' => [
            ['label' => 'Group Base (accountgbs)', 'route' => 'accounts.bases'],
            ['label' => 'Account Groups (accountg)', 'route' => 'accounts.groups'],
            ['label' => 'Ledgers (accountm)', 'route' => 'accounts.ledgers'],
        ]],
        ['type' => 'group', 'label' => 'Vouchers', 'icon' => 'receipt', 'match' => ['accounts.vouchers.*', 'accounts.stock-values*', 'accounts.reconciliation.*'], 'items' => [
            ['label' => 'All Vouchers', 'route' => 'accounts.vouchers.index'],
            ['label' => 'New Voucher', 'route' => 'accounts.vouchers.create'],
            ['label' => 'Closing Stock', 'route' => 'accounts.stock-values'],
            ['label' => 'Bank Reconciliation', 'route' => 'accounts.reconciliation.index'],
        ]],
        ['type' => 'group', 'label' => 'Financial Reports', 'icon' => 'chart', 'match' => ['accounts.reports.*'], 'items' => [
            ['label' => 'Day Book', 'route' => 'accounts.reports.daybook'],
            ['label' => 'Journal Register', 'route' => 'accounts.reports.journal-register'],
            ['label' => 'General Ledger', 'route' => 'accounts.reports.general-ledger'],
            ['label' => 'Cash Book', 'route' => 'accounts.reports.cash-book'],
            ['label' => 'Bank Book', 'route' => 'accounts.reports.bank-book'],
            ['label' => 'Cash Flow', 'route' => 'accounts.reports.cash-flow'],
            ['label' => 'Receipts & Payments', 'route' => 'accounts.reports.receipts-payments'],
            ['label' => 'Trial Balance', 'route' => 'accounts.reports.trial-balance'],
            ['label' => 'Trial Balance (Group)', 'route' => 'accounts.reports.trial-balance-group'],
            ['label' => 'Group Summary', 'route' => 'accounts.reports.group-summary'],
            ['label' => 'Trading, P&L A/c', 'route' => 'accounts.reports.trading-profit-loss'],
            ['label' => 'Profit & Loss', 'route' => 'accounts.reports.profit-loss'],
            ['label' => 'Balance Sheet', 'route' => 'accounts.reports.balance-sheet'],
            ['label' => 'BS Schedules', 'route' => 'accounts.reports.balance-sheet-schedules'],
            ['label' => 'Receivable & Payable', 'route' => 'accounts.reports.receivable-payable'],
            ['label' => 'Outstanding', 'route' => 'accounts.reports.outstanding'],
        ]],

        ['type' => 'section', 'label' => 'Reports'],
        ['type' => 'group', 'label' => 'Scheme Reports', 'icon' => 'doc', 'match' => ['admin.reports.*'], 'items' => [
            ['label' => 'Customer Report', 'route' => 'admin.reports.customers'],
            ['label' => 'Joiners', 'route' => 'admin.reports.joiners'],
            ['label' => 'Scheme Report', 'route' => 'admin.reports.schemes'],
            ['label' => 'Collection Report', 'route' => 'admin.reports.collection'],
            ['label' => 'Payment Pending', 'route' => 'admin.reports.pending'],
            ['label' => 'Agent Pending', 'route' => 'admin.reports.agent-pending'],
            ['label' => 'Gram Summary', 'route' => 'admin.reports.gram-summary'],
        ]],

        ['type' => 'section', 'label' => 'Masters'],
        ['type' => 'group', 'label' => 'Location', 'icon' => 'map', 'match' => ['admin.master.index'], 'matchParam' => ['countries', 'states', 'districts'], 'items' => [
            ['label' => 'Countries', 'route' => 'admin.master.index', 'param' => 'countries'],
            ['label' => 'States', 'route' => 'admin.master.index', 'param' => 'states'],
            ['label' => 'Districts', 'route' => 'admin.master.index', 'param' => 'districts'],
        ]],
        ['type' => 'group', 'label' => 'Metals & Rates', 'icon' => 'gem', 'matchParam' => ['metals', 'metal-rates'], 'items' => [
            ['label' => 'Metals', 'route' => 'admin.master.index', 'param' => 'metals'],
            ['label' => 'Gram Rate', 'route' => 'admin.master.index', 'param' => 'metal-rates'],
        ]],
        ['type' => 'group', 'label' => 'Products', 'icon' => 'box', 'matchParam' => ['categories', 'products', 'offers'], 'items' => [
            ['label' => 'Categories', 'route' => 'admin.master.index', 'param' => 'categories'],
            ['label' => 'Products', 'route' => 'admin.master.index', 'param' => 'products'],
            ['label' => 'Offers', 'route' => 'admin.master.index', 'param' => 'offers'],
        ]],
        ['type' => 'group', 'label' => 'Content', 'icon' => 'image', 'matchParam' => ['banners', 'banner-contents', 'terms', 'teams', 'messages'], 'items' => [
            ['label' => 'Banner Images', 'route' => 'admin.master.index', 'param' => 'banners'],
            ['label' => 'Banner Content', 'route' => 'admin.master.index', 'param' => 'banner-contents'],
            ['label' => 'Terms & Conditions', 'route' => 'admin.master.index', 'param' => 'terms'],
            ['label' => 'Our Team', 'route' => 'admin.master.index', 'param' => 'teams'],
            ['label' => 'Custom Messages', 'route' => 'admin.master.index', 'param' => 'messages'],
        ]],
        ['type' => 'group', 'label' => 'Settings', 'icon' => 'cog', 'matchParam' => ['banks', 'users'], 'match' => ['admin.settings'], 'items' => [
            ['label' => 'Bank Details', 'route' => 'admin.master.index', 'param' => 'banks'],
            ['label' => 'Users & Roles', 'route' => 'admin.master.index', 'param' => 'users'],
            ['label' => 'Company Settings', 'route' => 'admin.settings'],
        ]],
    ];

    $icons = [
        'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        'users' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5z',
        'badge' => 'M12 2l7 4v6c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6l7-4z',
        'layers' => 'M12 3l9 5-9 5-9-5 9-5zM3 13l9 5 9-5M3 17l9 5 9-5',
        'wallet' => 'M3 7a2 2 0 012-2h12a2 2 0 012 2v1h1a1 1 0 011 1v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm14 5a1 1 0 100 2 1 1 0 000-2z',
        'star' => 'M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 17.8 5.7 21.2 7 14.2 2 9.4l7-.9L12 2z',
        'gift' => 'M20 12v9H4v-9M2 7h20v5H2V7zm10 14V7m0 0H8.5A2.5 2.5 0 116 4.5C6 7 12 7 12 7zm0 0h3.5A2.5 2.5 0 1018 4.5C18 7 12 7 12 7z',
        'book' => 'M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3V4zm0 0v13a3 3 0 003 3',
        'receipt' => 'M6 2h12v20l-3-2-3 2-3-2-3 2V2zm2 5h8M8 11h8M8 15h5',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'doc' => 'M7 3h7l5 5v13H7V3zm7 0v5h5M9 13h6M9 17h6',
        'map' => 'M9 3L3 6v15l6-3 6 3 6-3V3l-6 3-6-3zm0 0v15m6-12v15',
        'gem' => 'M6 3h12l3 6-9 12L3 9l3-6zm-3 6h18M9 3l3 18M15 3l-3 18',
        'box' => 'M21 8l-9-5-9 5 9 5 9-5zm0 0v8l-9 5-9-5V8',
        'image' => 'M3 5h18v14H3V5zm3 10l3.5-4 3 3.5L15 11l3 4',
        'cog' => 'M12 15a3 3 0 100-6 3 3 0 000 6zm8-3a8 8 0 01-.2 1.7l2 1.6-2 3.4-2.4-1a8 8 0 01-2.9 1.7L14 22h-4l-.5-2.6a8 8 0 01-2.9-1.7l-2.4 1-2-3.4 2-1.6A8 8 0 014 12c0-.6.1-1.1.2-1.7l-2-1.6 2-3.4 2.4 1a8 8 0 012.9-1.7L10 2h4l.5 2.6a8 8 0 012.9 1.7l2.4-1 2 3.4-2 1.6c.1.6.2 1.1.2 1.7z',
        'tool' => 'M14.7 6.3a4 4 0 00-5 5L3 18v3h3l6.7-6.7a4 4 0 005-5l-2.8 2.8-2-2 2.8-2.8z',
    ];

    $isActive = function ($item) {
        foreach (($item['match'] ?? []) as $p) {
            if (request()->routeIs($p)) return true;
        }
        if (! empty($item['matchParam']) && request()->routeIs('admin.master.index')) {
            return in_array(request()->route('entity'), $item['matchParam']);
        }
        foreach (($item['items'] ?? []) as $sub) {
            if (isset($sub['param'])) {
                if (request()->routeIs('admin.master.index') && request()->route('entity') === $sub['param']) return true;
            } elseif (request()->routeIs($sub['route'])) {
                return true;
            }
        }
        return false;
    };
@endphp

{{-- Brand --}}
<div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-5">
    <div class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-700 text-sm font-black text-white shadow-lg">
        {{ strtoupper(substr($company, 0, 2)) }}
    </div>
    <div class="min-w-0">
        <div class="truncate text-[14px] font-bold leading-tight text-white">{{ $company }}</div>
        <div class="truncate text-[11px] text-ink-500">{{ $tagline }}</div>
    </div>
    <button @click="open = false" class="ml-auto rounded-md p-1.5 text-ink-400 hover:bg-white/10 hover:text-white lg:hidden">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

{{-- Nav --}}
<nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-6">
    @foreach ($nav as $item)
        @if ($item['type'] === 'section')
            <div class="nav-section">{{ $item['label'] }}</div>

        @elseif ($item['type'] === 'link')
            <a href="{{ route($item['route']) }}"
               class="nav-link {{ request()->routeIs($item['route']) ? 'nav-link-active' : '' }}">
                <svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="{{ $icons[$item['icon']] }}"/>
                </svg>
                {{ $item['label'] }}
            </a>

        @else
            @php $active = $isActive($item); @endphp
            <div x-data="{ o: {{ $active ? 'true' : 'false' }} }">
                <button @click="o = !o" class="nav-link w-full {{ $active ? 'text-white' : '' }}">
                    <svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="{{ $icons[$item['icon']] ?? $icons['doc'] }}"/>
                    </svg>
                    <span class="flex-1 text-left">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                    @endif
                    <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="o && 'rotate-90'"
                         fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div x-show="o" x-cloak x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     class="space-y-0.5 pb-1 pt-0.5">
                    @foreach ($item['items'] as $sub)
                        @php
                            $url = isset($sub['param']) ? route($sub['route'], $sub['param']) : route($sub['route']);
                            $on = isset($sub['param'])
                                ? (request()->routeIs('admin.master.index') && request()->route('entity') === $sub['param'])
                                : request()->routeIs($sub['route']);
                        @endphp
                        <a href="{{ $url }}" class="nav-sub {{ $on ? 'nav-sub-active' : '' }}">
                            <span class="h-1 w-1 rounded-full {{ $on ? 'bg-brand-400' : 'bg-ink-600' }}"></span>
                            {{ $sub['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</nav>
