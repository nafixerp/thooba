<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Services\BankReconciliationService;
use App\Services\SchemeService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Checking direct UPI collections against the bank statement.
 *
 * A payment made through a `upi://pay` link has nothing behind it that can
 * prove the money arrived - the app's "Status=SUCCESS" is the customer's
 * device talking. This screen is where that claim meets the evidence.
 */
class ReconciliationController extends Controller
{
    public function __construct(
        protected BankReconciliationService $reconcile,
        protected SchemeService $schemes,
    ) {}

    public function index(Request $request)
    {
        $status = strtoupper((string) $request->input('status', 'UNMATCHED'));

        $lines = BankStatementLine::with(['payment.customer:id,name,reg_no', 'bank:id,bank_name'])
            ->credits()
            ->when(
                in_array($status, ['UNMATCHED', 'MATCHED', 'IGNORED'], true),
                fn ($q) => $q->where('status', $status)
            )
            ->when($request->from && $request->to, fn ($q) => $q->whereBetween('txn_date', [$request->from, $request->to]))
            ->orderByDesc('txn_date')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('accounts.reconciliation', [
            'lines' => $lines,
            'status' => $status,
            'banks' => Bank::active()->get(['id', 'bank_name']),
            // The other half of the story: what customers claim the bank has
            // never shown. This is the list that catches a mistake or a lie.
            'unmatchedClaims' => $this->reconcile->claimedPayments(),
            'counts' => [
                'unmatched' => BankStatementLine::credits()->unmatched()->count(),
                'matched' => BankStatementLine::credits()->matched()->count(),
                'ignored' => BankStatementLine::credits()->where('status', 'IGNORED')->count(),
            ],
            'from' => $request->from,
            'to' => $request->to,
        ]);
    }

    /** Upload a statement CSV exported from net banking. */
    public function import(Request $request)
    {
        $data = $request->validate([
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:8192'],
            'bank_id' => ['nullable', 'exists:banks,id'],
        ]);

        try {
            $rows = $this->reconcile->parse($data['statement']->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['statement' => 'No transaction rows were found in that file.']);
        }

        $result = $this->reconcile->import($rows, $data['bank_id'] ?? null, $request->user()?->id);
        $matched = $this->reconcile->matchAll();

        $message = "{$result['imported']} line(s) imported";

        if ($result['duplicates'] > 0) {
            $message .= ", {$result['duplicates']} already held";
        }

        $message .= ". Matched {$matched['exact']} by reference";

        if ($matched['likely'] > 0) {
            $message .= " and {$matched['likely']} by amount and date - check those before approving";
        }

        return back()->with('success', $message.'.');
    }

    /** Re-run matching without importing anything new. */
    public function rematch()
    {
        $matched = $this->reconcile->matchAll();

        return back()->with(
            'success',
            "Matched {$matched['exact']} by reference and {$matched['likely']} by amount and date."
        );
    }

    /**
     * Approve the payment behind a matched line. This is the moment the money
     * becomes real: the voucher posts and the scheme is credited.
     */
    public function approve(Request $request, int $id)
    {
        $line = BankStatementLine::with('payment')->findOrFail($id);

        if (! $line->payment) {
            return back()->withErrors(['error' => 'That line is not matched to a payment.']);
        }

        if ($line->payment->status === 'APPROVED') {
            return back()->with('success', 'That payment was already approved.');
        }

        $this->schemes->approvePayment($line->payment, $request->user()?->id);

        return back()->with('success', "Payment {$line->payment->receipt_no} approved against the bank.");
    }

    /**
     * Approve every line matched on a reference in one go.
     *
     * Only ORDER_NO and UTR matches - those are the bank quoting a reference
     * only the real payment could carry. An amount-and-date guess still needs
     * a person to look at it.
     */
    public function approveMatched(Request $request)
    {
        $lines = BankStatementLine::with('payment')
            ->matched()
            ->whereIn('match_type', ['ORDER_NO', 'UTR'])
            ->whereHas('payment', fn ($q) => $q->where('status', 'PENDING'))
            ->get();

        $done = 0;

        foreach ($lines as $line) {
            $this->schemes->approvePayment($line->payment, $request->user()?->id);
            $done++;
        }

        return back()->with('success', $done
            ? "$done payment(s) approved against the bank statement."
            : 'Nothing to approve - no reference matches are waiting.');
    }

    /** Attach a line to a payment by hand, when the narration gave nothing. */
    public function link(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
        ]);

        $line = BankStatementLine::findOrFail($id);
        $payment = Payment::findOrFail($data['payment_id']);

        if ($payment->statementLine && $payment->statementLine->id !== $line->id) {
            return back()->withErrors(['error' => 'That payment is already matched to another line.']);
        }

        $line->update([
            'payment_id' => $payment->id,
            'match_type' => 'MANUAL',
            'status' => 'MATCHED',
        ]);

        return back()->with('success', "Linked to {$payment->receipt_no}.");
    }

    /** Not a customer payment - a transfer, interest, the shop's own deposit. */
    public function ignore(Request $request, int $id)
    {
        $line = BankStatementLine::findOrFail($id);

        $line->update([
            'status' => 'IGNORED',
            'payment_id' => null,
            'match_type' => null,
            'note' => $request->input('note'),
        ]);

        return back()->with('success', 'Line set aside.');
    }

    /** Undo a match or an ignore. */
    public function reset(int $id)
    {
        BankStatementLine::findOrFail($id)->update([
            'status' => 'UNMATCHED',
            'payment_id' => null,
            'match_type' => null,
            'note' => null,
        ]);

        return back()->with('success', 'Line reopened.');
    }
}
