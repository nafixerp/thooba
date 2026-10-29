<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * -----------------------------------------------------------------------------
 *  BankReconciliationService - proving a direct UPI payment actually arrived
 * -----------------------------------------------------------------------------
 *
 * A payment made through a `upi://pay` link has no aggregator behind it, so the
 * "Status=SUCCESS" the UPI app hands back is a claim, not proof. The bank
 * statement is the proof.
 *
 * The shop exports the statement from net banking, uploads the CSV, and every
 * credit line is matched against the payments customers have claimed:
 *
 *   ORDER_NO      the narration carries our own order number - the app puts it
 *                 in the UPI `tr` field, and most banks pass it through. The
 *                 strongest signal there is.
 *   UTR           the narration or reference carries the UTR the customer
 *                 typed in when confirming.
 *   AMOUNT_DATE   no reference to go on, but exactly one claimed payment has
 *                 that amount within a few days. A guess; a human agrees to it.
 *
 * Only the first two are safe to approve in bulk. What the screen is really
 * for is the two lists nobody looks at otherwise:
 *
 *   - money in the bank that no customer claimed  (unmatched credits)
 *   - customers claiming money the bank never saw (unmatched payments)
 */
class BankReconciliationService
{
    /** How far either side of a statement date an amount-only match may sit. */
    public const DATE_WINDOW_DAYS = 3;

    /** Header names Indian bank exports actually use. */
    protected const COLUMNS = [
        'date' => ['date', 'txn date', 'transaction date', 'value date', 'tran date', 'posting date', 'txndate'],
        'narration' => ['narration', 'description', 'particulars', 'remarks', 'transaction remarks', 'details', 'transaction details'],
        'ref' => ['chq./ref.no.', 'chq/ref number', 'ref no', 'ref no.', 'reference', 'reference no', 'cheque no', 'chq no', 'utr', 'rrn', 'transaction id'],
        'debit' => ['withdrawal', 'withdrawal amt.', 'withdrawal amt', 'withdrawal amount', 'debit', 'debit amount', 'dr', 'paid out'],
        'credit' => ['deposit', 'deposit amt.', 'deposit amt', 'deposit amount', 'credit', 'credit amount', 'cr', 'paid in'],
        'balance' => ['balance', 'closing balance', 'running balance', 'balance amt'],
    ];

    /**
     * Read a statement CSV into rows. Throws when the header cannot be found,
     * rather than importing nonsense.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path): array
    {
        $handle = @fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $map = null;
        $rows = [];

        try {
            while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($cells === [null] || $cells === false) {
                    continue;
                }

                // Bank exports carry a few lines of account blurb before the
                // real header, so look for the header rather than assume row 1.
                if ($map === null) {
                    $candidate = $this->mapHeader($cells);

                    if ($candidate !== null) {
                        $map = $candidate;
                    }

                    continue;
                }

                $row = $this->row($cells, $map);

                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($map === null) {
            throw new RuntimeException(
                'No statement header was found. The file needs a row with a date column '
                .'and a deposit/credit column - export it as CSV from net banking.'
            );
        }

        return $rows;
    }

    /**
     * Import parsed rows, skipping ones already held.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, duplicates: int, skipped: int}
     */
    public function import(array $rows, ?int $bankId = null, ?int $userId = null): array
    {
        $imported = 0;
        $duplicates = 0;

        foreach ($rows as $row) {
            $fingerprint = BankStatementLine::fingerprintFor(
                $row['txn_date'],
                $row['debit'],
                $row['credit'],
                $row['narration'],
                $row['ref_no'],
            );

            if (BankStatementLine::where('fingerprint', $fingerprint)->exists()) {
                $duplicates++;

                continue;
            }

            BankStatementLine::create([
                'bank_id' => $bankId,
                'txn_date' => $row['txn_date'],
                'narration' => $row['narration'],
                'ref_no' => $row['ref_no'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'balance' => $row['balance'],
                'fingerprint' => $fingerprint,
                'status' => 'UNMATCHED',
                'imported_by' => $userId,
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => 0,
        ];
    }

    /**
     * Match every unmatched credit line against the payments customers have
     * claimed but nobody has approved.
     *
     * @return array{exact: int, likely: int}
     */
    public function matchAll(): array
    {
        $lines = BankStatementLine::unmatched()->credits()->orderBy('txn_date')->get();

        if ($lines->isEmpty()) {
            return ['exact' => 0, 'likely' => 0];
        }

        $candidates = $this->claimedPayments();
        $exact = 0;
        $likely = 0;

        // Reference matches first, so a weaker amount-only guess can never
        // steal a payment a stronger line would have taken.
        foreach ([true, false] as $strongPass) {
            foreach ($lines as $line) {
                if ($line->payment_id) {
                    continue;
                }

                $hit = $strongPass
                    ? $this->matchByReference($line, $candidates)
                    : $this->matchByAmountAndDate($line, $candidates);

                if ($hit === null) {
                    continue;
                }

                [$payment, $type] = $hit;

                $line->update([
                    'payment_id' => $payment->id,
                    'match_type' => $type,
                    'status' => 'MATCHED',
                ]);

                $candidates = $candidates->reject(fn ($p) => $p->id === $payment->id)->values();

                $strongPass ? $exact++ : $likely++;
            }
        }

        return ['exact' => $exact, 'likely' => $likely];
    }

    /**
     * Payments a customer says they made that nobody has confirmed yet.
     *
     * @return Collection<int, Payment>
     */
    public function claimedPayments(): Collection
    {
        return Payment::with('customer:id,name,reg_no')
            ->where('status', 'PENDING')
            ->whereDoesntHave('statementLine')
            ->orderBy('payment_date')
            ->get();
    }

    /**
     * The order numbers behind a payment, so a narration quoting one matches.
     *
     * @return array<int, string>
     */
    protected function referencesFor(Payment $payment): array
    {
        $refs = [];

        if (filled($payment->txn_ref)) {
            $refs[] = strtoupper(trim($payment->txn_ref));
        }

        foreach ($payment->paymentOrders as $order) {
            $refs[] = strtoupper($order->order_no);

            if (filled($order->gateway_payment_id)) {
                $refs[] = strtoupper($order->gateway_payment_id);
            }
        }

        // A stray "1" or "OK" in a narration must not match anything.
        return array_values(array_unique(array_filter(
            $refs,
            fn ($r) => strlen($r) >= 6,
        )));
    }

    /**
     * @param  Collection<int, Payment>  $candidates
     * @return array{0: Payment, 1: string}|null
     */
    protected function matchByReference(BankStatementLine $line, Collection $candidates): ?array
    {
        $haystack = $line->searchText();

        foreach ($candidates as $payment) {
            foreach ($this->referencesFor($payment) as $ref) {
                if (! str_contains($haystack, $ref)) {
                    continue;
                }

                $isOrderNo = $payment->paymentOrders
                    ->contains(fn ($o) => strtoupper($o->order_no) === $ref);

                return [$payment, $isOrderNo ? 'ORDER_NO' : 'UTR'];
            }
        }

        return null;
    }

    /**
     * Same amount, near enough the same day, and only one possibility. Anything
     * ambiguous is left for a person.
     *
     * @param  Collection<int, Payment>  $candidates
     * @return array{0: Payment, 1: string}|null
     */
    protected function matchByAmountAndDate(BankStatementLine $line, Collection $candidates): ?array
    {
        $credit = round((float) $line->credit, 2);
        $date = $line->txn_date;

        $hits = $candidates->filter(function (Payment $p) use ($credit, $date) {
            if (abs(round((float) $p->total, 2) - $credit) > 0.01) {
                return false;
            }

            return abs($p->payment_date->diffInDays($date)) <= self::DATE_WINDOW_DAYS;
        })->values();

        return $hits->count() === 1 ? [$hits->first(), 'AMOUNT_DATE'] : null;
    }

    /* ===================================================================== */
    /*  CSV plumbing                                                         */
    /* ===================================================================== */

    /**
     * Is this row the header? Returns column index by role when it is.
     *
     * @param  array<int, string|null>  $cells
     * @return array<string, int>|null
     */
    protected function mapHeader(array $cells): ?array
    {
        $map = [];

        foreach ($cells as $i => $cell) {
            $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $cell)));

            if ($name === '') {
                continue;
            }

            foreach (self::COLUMNS as $role => $aliases) {
                if (isset($map[$role])) {
                    continue;
                }

                if (in_array($name, $aliases, true)) {
                    $map[$role] = $i;
                    break;
                }
            }
        }

        // A statement is only usable if we can read a date and money in.
        $hasAmount = isset($map['credit']) || isset($map['debit']);

        return isset($map['date']) && $hasAmount ? $map : null;
    }

    /**
     * @param  array<int, string|null>  $cells
     * @param  array<string, int>  $map
     * @return array<string, mixed>|null
     */
    protected function row(array $cells, array $map): ?array
    {
        $at = fn (string $role) => isset($map[$role]) ? trim((string) ($cells[$map[$role]] ?? '')) : '';

        $date = $this->date($at('date'));

        if ($date === null) {
            // Totals, page footers, blank rows.
            return null;
        }

        $debit = $this->amount($at('debit'));
        $credit = $this->amount($at('credit'));

        if ($debit <= 0 && $credit <= 0) {
            return null;
        }

        return [
            'txn_date' => $date,
            'narration' => $at('narration') ?: null,
            'ref_no' => $at('ref') ?: null,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => isset($map['balance']) ? ($this->amount($at('balance')) ?: null) : null,
        ];
    }

    /** The formats Indian bank exports use, in the order they turn up. */
    protected function date(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'd-M-Y', 'd/m/y', 'd-m-y', 'Y-m-d', 'd M Y', 'd.m.Y'] as $format) {
            try {
                $d = Carbon::createFromFormat($format, $value);

                // createFromFormat happily accepts "31/31/2026"; reject those.
                if ($d && $d->format($format) === $value) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
                // try the next one
            }
        }

        return null;
    }

    /** "1,23,456.78" / "(500.00)" / "500.00 Cr" -> float. */
    protected function amount(string $value): float
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/\d/', $value)) {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return round((float) $clean, 2);
    }
}
