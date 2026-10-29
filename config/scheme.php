<?php

/*
|--------------------------------------------------------------------------
| Scheme ERP configuration
|--------------------------------------------------------------------------
| Ella accounting control ledger accode-um ivide ninnu edukkunnu.
| Chart of Accounts seeder ee accode thanne create cheyyunnu.
*/

return [

    'currency' => env('APP_CURRENCY', 'INR'),
    'symbol'   => env('APP_CURRENCY_SYMBOL', '₹'),

    // Financial year start, format MM-DD
    'fy_start' => env('FY_START', '04-01'),

    'gst_percent' => (float) env('GST_PERCENT', 3),

    /*
     | How GST sits on a scheme collection. Settings -> GST mode overrides this.
     |
     |   EXCLUSIVE  added on top    - customer pays installment + GST
     |   INCLUSIVE  already inside  - the installment contains the GST
     |   NONE       not charged     - amount received = amount credited
     |
     | Whichever is chosen, money received always equals scheme credit + GST,
     | which is what the receipt voucher posts.
     */
    'gst_mode' => env('GST_MODE', 'EXCLUSIVE'),

    'gst_modes' => [
        'EXCLUSIVE' => 'Added on top of the installment',
        'INCLUSIVE' => 'Already included in the installment',
        'NONE' => 'Not charged on collections',
    ],

    /*
     | Control ledger accounts used by auto posting.
     */
    'accounts' => [
        'cash'             => env('ACC_CASH', '1001'),
        'bank'             => env('ACC_BANK', '1002'),
        'scheme_deposit'   => env('ACC_SCHEME_DEPOSIT', '2001'),
        'gst_payable'      => env('ACC_GST_PAYABLE', '2002'),
        'agent_commission' => env('ACC_AGENT_COMMISSION', '5001'),
        'sales'            => env('ACC_SALES', '4001'),
        'roundoff'         => env('ACC_ROUNDOFF', '5099'),
        'stock'            => env('ACC_STOCK', '1010'),
        'purchase'         => env('ACC_PURCHASE', '5101'),
    ],

    /*
     | Trading / Profit & Loss statement sections.
     |
     | Ported from dua soft's accountm.tplpos. Every P&L ledger sits in exactly
     | one section; the two trading sections each close with their own Gross
     | Profit c/d, which is then carried down into the Profit & Loss A/c.
     */
    'tpl_sections' => [
        1 => 'Trading A/c',
        2 => 'Trading A/c II',
        0 => 'Profit & Loss A/c',
    ],

    /*
     | Legacy SunDbCrAutoCorrect: report a Sundry Debtor that carries a credit
     | balance under Sundry Creditors, and the reverse, instead of showing a
     | negative figure on the wrong side of the Balance Sheet.
     */
    'sundry_auto_correct' => (bool) env('SUNDRY_AUTO_CORRECT', true),

    /*
     | Voucher types used across the app.
     */
    'voucher_types' => [
        'RECEIPT'  => 'Receipt',
        'PAYMENT'  => 'Payment',
        'JOURNAL'  => 'Journal',
        'CONTRA'   => 'Contra',
        'SALES'    => 'Sales',
        'PURCHASE' => 'Purchase',
        'CREDITNOTE' => 'Credit Note',
        'DEBITNOTE'  => 'Debit Note',
        'OPENING'  => 'Opening Balance',
    ],

    'payment_modes' => ['CASH', 'UPI', 'CARD', 'BANK', 'CHEQUE', 'WALLET'],

    'payment_intervals' => ['DAILY', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY'],
];
