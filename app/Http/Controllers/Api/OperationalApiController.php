<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\Branch;
use App\Models\CustomerLedger;
use App\Models\CustomerScheme;
use App\Models\KycDocument;
use App\Models\MetalRate;
use App\Models\Notification;
use App\Models\Refund;
use App\Models\SchemeClosure;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Term;
use App\Models\TermsAcceptance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OperationalApiController extends Controller
{
    public function branches()
    {
        return $this->ok(Branch::active()->orderBy('name')->get()->map(fn ($b) => [
            'id' => $b->id,
            'code' => $b->code,
            'name' => $b->name,
            'manager_name' => $b->manager_name,
            'phone' => $b->phone,
            'email' => $b->email,
            'address' => $b->address,
            'city' => $b->city,
            'state' => $b->state,
            'pincode' => $b->pincode,
            'latitude' => $b->latitude ? (float) $b->latitude : null,
            'longitude' => $b->longitude ? (float) $b->longitude : null,
        ]));
    }

    public function appVersion(Request $request)
    {
        $platform = strtoupper($request->input('platform', 'ANDROID'));
        $row = AppVersion::active()->where('platform', $platform)->latest('build_number')->first();

        return $this->ok($row ? [
            'platform' => $row->platform,
            'version' => $row->version,
            'build_number' => $row->build_number,
            'min_supported_version' => $row->min_supported_version,
            'min_supported_build' => $row->min_supported_build,
            'force_update' => (bool) $row->force_update,
            'maintenance_mode' => (bool) $row->maintenance_mode,
            'store_url' => $row->store_url,
            'release_notes' => $row->release_notes,
        ] : null);
    }

    public function acceptTerms(Request $request)
    {
        $data = $request->validate([
            'term_id' => ['nullable', 'exists:terms,id'],
            'type' => ['nullable', 'string', 'max:40'],
            'version' => ['nullable', 'string', 'max:30'],
        ]);

        if (empty($data['type']) && ! empty($data['term_id'])) {
            $data['type'] = Term::find($data['term_id'])?->type;
        }

        $row = TermsAcceptance::create([
            'customer_id' => $request->user()->id,
            'term_id' => $data['term_id'] ?? null,
            'type' => strtoupper($data['type'] ?? 'TERMS'),
            'version' => $data['version'] ?? null,
            'accepted_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return $this->ok(['id' => $row->id], 'Terms accepted');
    }

    public function kycDocuments(Request $request)
    {
        return $this->ok($request->user()->kycDocuments()->latest('id')->get()->map(fn ($d) => [
            'id' => $d->id,
            'document_type' => $d->document_type,
            'document_no' => $d->document_no,
            'front_file' => storage_url($d->front_file),
            'back_file' => $d->back_file ? storage_url($d->back_file) : null,
            'status' => $d->status,
            'remarks' => $d->remarks,
        ]));
    }

    public function uploadKyc(Request $request)
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:AADHAAR,PAN,PASSPORT,VOTER_ID,DRIVING_LICENSE,OTHER'],
            'document_no' => ['nullable', 'string', 'max:80'],
            'front_file' => ['required', 'file', 'max:5120'],
            'back_file' => ['nullable', 'file', 'max:5120'],
        ]);

        $document = KycDocument::create([
            'customer_id' => $request->user()->id,
            'document_type' => $data['document_type'],
            'document_no' => $data['document_no'] ?? null,
            'front_file' => $request->file('front_file')->store('kyc', 'public'),
            'back_file' => $request->hasFile('back_file') ? $request->file('back_file')->store('kyc', 'public') : null,
            'status' => 'PENDING',
        ]);

        return $this->ok(['id' => $document->id, 'status' => $document->status], 'KYC document uploaded');
    }

    public function ledger(Request $request)
    {
        $rows = CustomerLedger::where('customer_id', $request->user()->id)
            ->when($request->customer_scheme_id, fn ($q, $v) => $q->where('customer_scheme_id', $v))
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(50);

        return $this->ok($rows->through(fn ($row) => [
            'date' => $row->entry_date->toDateString(),
            'type' => $row->entry_type,
            'description' => $row->description,
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
            'balance' => (float) $row->balance,
        ]));
    }

    public function refunds(Request $request)
    {
        return $this->ok(Refund::where('customer_id', $request->user()->id)
            ->latest('id')
            ->paginate(25)
            ->through(fn ($r) => $this->refundRow($r)));
    }

    public function requestRefund(Request $request)
    {
        $data = $request->validate([
            'customer_scheme_id' => ['nullable', 'exists:customer_schemes,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'refund_mode' => ['nullable', 'in:ORIGINAL,CASH,UPI,BANK,CARD'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($data['customer_scheme_id']) && ! $this->ownsScheme($request->user()->id, (int) $data['customer_scheme_id'])) {
            return $this->fail('That scheme does not belong to you.', 403);
        }

        $refund = Refund::create($data + [
            'refund_no' => Refund::nextNo(),
            'customer_id' => $request->user()->id,
            'refund_mode' => $data['refund_mode'] ?? 'ORIGINAL',
            'requested_by_type' => get_class($request->user()),
            'requested_by_id' => $request->user()->id,
        ]);

        return $this->ok($this->refundRow($refund), 'Refund request submitted');
    }

    public function requestClosure(Request $request, int $customerSchemeId)
    {
        $customerScheme = CustomerScheme::where('customer_id', $request->user()->id)->findOrFail($customerSchemeId);

        $data = $request->validate([
            'closure_type' => ['nullable', 'in:MATURITY,CANCELLATION,REDEMPTION,TRANSFER'],
            'purchase_invoice_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $closure = SchemeClosure::create([
            'closure_no' => SchemeClosure::nextNo(),
            'customer_id' => $request->user()->id,
            'customer_scheme_id' => $customerScheme->id,
            'scheme_id' => $customerScheme->scheme_id,
            'closure_type' => $data['closure_type'] ?? 'MATURITY',
            'paid_amount' => $customerScheme->total_paid,
            'bonus_amount' => $customerScheme->bonus_amount,
            'payable_amount' => ((float) $customerScheme->total_paid + (float) $customerScheme->bonus_amount),
            'purchase_invoice_no' => $data['purchase_invoice_no'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->ok($this->closureRow($closure), 'Scheme closure request submitted');
    }

    public function closures(Request $request)
    {
        return $this->ok(SchemeClosure::where('customer_id', $request->user()->id)
            ->latest('id')
            ->paginate(25)
            ->through(fn ($c) => $this->closureRow($c)));
    }

    public function tickets(Request $request)
    {
        $actor = $request->user();

        return $this->ok(SupportTicket::where('owner_type', get_class($actor))
            ->where('owner_id', $actor->id)
            ->latest('id')
            ->paginate(25)
            ->through(fn ($t) => $this->ticketRow($t)));
    }

    public function createTicket(Request $request)
    {
        $actor = $request->user();
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string'],
            'category' => ['nullable', 'in:PAYMENT,SCHEME,KYC,APP,GENERAL'],
            'priority' => ['nullable', 'in:LOW,NORMAL,HIGH,URGENT'],
        ]);

        $ticket = SupportTicket::create([
            'ticket_no' => SupportTicket::nextNo(),
            'owner_type' => get_class($actor),
            'owner_id' => $actor->id,
            'customer_id' => $actor instanceof \App\Models\Customer ? $actor->id : null,
            'agent_id' => $actor instanceof \App\Models\Agent ? $actor->id : null,
            'subject' => $data['subject'],
            'category' => $data['category'] ?? 'GENERAL',
            'priority' => $data['priority'] ?? 'NORMAL',
        ]);

        $ticket->replies()->create([
            'author_type' => get_class($actor),
            'author_id' => $actor->id,
            'message' => $data['message'],
        ]);

        return $this->ok($this->ticketRow($ticket->load('replies')), 'Ticket created');
    }

    public function ticket(Request $request, int $id)
    {
        $ticket = $this->ownedTicket($request->user(), $id)->load('replies');

        return $this->ok($this->ticketRow($ticket, true));
    }

    public function replyTicket(Request $request, int $id)
    {
        $actor = $request->user();
        $ticket = $this->ownedTicket($actor, $id);
        $data = $request->validate([
            'message' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'author_type' => get_class($actor),
            'author_id' => $actor->id,
            'message' => $data['message'],
            'attachment' => $request->hasFile('attachment') ? $request->file('attachment')->store('support', 'public') : null,
        ]);

        return $this->ok(['id' => $reply->id], 'Reply added');
    }

    public function notifications(Request $request)
    {
        $actor = $request->user();

        return $this->ok(Notification::where('notifiable_type', get_class($actor))
            ->where('notifiable_id', $actor->id)
            ->latest('id')
            ->paginate(25)
            ->through(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'sent_at' => $n->sent_at?->toIso8601String(),
            ]));
    }

    public function readNotification(Request $request, int $id)
    {
        $actor = $request->user();

        Notification::where('notifiable_type', get_class($actor))
            ->where('notifiable_id', $actor->id)
            ->whereKey($id)
            ->update(['read_at' => now()]);

        return $this->ok(null, 'Notification marked read');
    }

    public function calculator(Request $request)
    {
        $data = $request->validate([
            'monthly_amount' => ['required', 'numeric', 'min:1'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'bonus_type' => ['nullable', 'in:NONE,PERCENT,AMOUNT,INSTALLMENT'],
            'bonus_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $principal = (float) $data['monthly_amount'] * (int) $data['months'];
        $bonus = match ($data['bonus_type'] ?? 'NONE') {
            'PERCENT' => $principal * ((float) ($data['bonus_value'] ?? 0) / 100),
            'AMOUNT' => (float) ($data['bonus_value'] ?? 0),
            'INSTALLMENT' => (float) $data['monthly_amount'] * (float) ($data['bonus_value'] ?? 1),
            default => 0,
        };
        $rate = MetalRate::latest_rate();

        return $this->ok([
            'principal' => round($principal, 2),
            'bonus' => round($bonus, 2),
            'maturity_amount' => round($principal + $bonus, 2),
            'estimated_gold_gram' => $rate > 0 ? round(($principal + $bonus) / $rate, 3) : 0,
            'gold_rate' => $rate,
        ]);
    }

    public function insights(Request $request)
    {
        $customer = $request->user();
        $active = $customer->schemes()->where('status', 'ACTIVE')->count();
        $overdue = $customer->schemes()
            ->whereHas('installments', fn ($q) => $q->where('status', 'OVERDUE'))
            ->count();

        return $this->ok([
            'active_schemes' => $active,
            'overdue_schemes' => $overdue,
            'payment_reliability' => $overdue === 0 ? 'GOOD' : 'NEEDS_ATTENTION',
            'suggested_reminder' => $overdue > 0 ? 'You have pending installments.' : 'Your schemes are on track.',
        ]);
    }

    protected function ownedTicket(Model $actor, int $id): SupportTicket
    {
        return SupportTicket::where('owner_type', get_class($actor))
            ->where('owner_id', $actor->id)
            ->findOrFail($id);
    }

    protected function ownsScheme(int $customerId, int $schemeId): bool
    {
        return CustomerScheme::where('id', $schemeId)->where('customer_id', $customerId)->exists();
    }

    protected function refundRow(Refund $refund): array
    {
        return [
            'id' => $refund->id,
            'refund_no' => $refund->refund_no,
            'amount' => (float) $refund->amount,
            'mode' => $refund->refund_mode,
            'reason' => $refund->reason,
            'status' => $refund->status,
            'processed_at' => $refund->processed_at?->toIso8601String(),
        ];
    }

    protected function closureRow(SchemeClosure $closure): array
    {
        return [
            'id' => $closure->id,
            'closure_no' => $closure->closure_no,
            'closure_type' => $closure->closure_type,
            'paid_amount' => (float) $closure->paid_amount,
            'bonus_amount' => (float) $closure->bonus_amount,
            'deduction_amount' => (float) $closure->deduction_amount,
            'payable_amount' => (float) $closure->payable_amount,
            'status' => $closure->status,
        ];
    }

    protected function ticketRow(SupportTicket $ticket, bool $withReplies = false): array
    {
        $row = [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
        ];

        if ($withReplies) {
            $row['replies'] = $ticket->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'message' => $reply->message,
                'attachment' => $reply->attachment ? storage_url($reply->attachment) : null,
                'internal' => (bool) $reply->internal,
                'created_at' => $reply->created_at?->toIso8601String(),
            ]);
        }

        return $row;
    }

    protected function ok($data, string $message = 'OK')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $code = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}
