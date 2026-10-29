<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Bank;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CustomerScheme;
use App\Models\Metal;
use App\Models\MetalRate;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Scheme;
use App\Models\Term;
use App\Services\SchemeService;
use Illuminate\Http\Request;

class SchemeApiController extends Controller
{
    public function __construct(protected SchemeService $service) {}

    /* =====================================================================
     |  PUBLIC (no token)
     ===================================================================== */

    /** GET /api/v1/home */
    public function home()
    {
        return $this->ok([
            'banners' => Banner::active()->orderBy('sortorder')->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'image' => storage_url($b->image),
                    'title' => $b->title,
                    'link' => $b->link,
                ]),
            'rates' => Metal::active()->get()->map(fn ($m) => [
                'metal' => $m->name,
                'code' => $m->code,
                'rate' => $m->currentRate(),
                'unit' => $m->unit,
            ]),
            'schemes' => Scheme::active()->get()->map(fn ($s) => $this->schemeArr($s)),
            'offers' => Offer::active()->with('product:id,name')->limit(10)->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'title' => $o->title,
                    'code' => $o->offer_code,
                    'discount' => (float) $o->discount,
                    'image' => $o->image ? storage_url($o->image) : null,
                ]),
        ]);
    }

    /**
     * GET /api/v1/app-config
     *
     * Branding, feature flags and gateway keys the Flutter app reads at
     * startup, so those can change without a new build.
     */
    public function appConfig()
    {
        $razorpay = app(\App\Services\RazorpayService::class);
        $setting = fn (string $key, $default = null) => \App\Models\Setting::get($key, $default);

        // The shop's own name wins over the .env default, so the app is
        // branded from the admin panel without a rebuild.
        $companyName = $setting('company_name') ?: config('mobile.app_name');

        return $this->ok([
            'app_name' => $companyName,
            'company_name' => $companyName,
            'tagline' => $setting('company_tagline'),
            'company_phone' => $setting('company_phone'),
            'company_email' => $setting('company_email'),
            'company_address' => $setting('company_address'),
            'currency' => config('scheme.currency'),
            'currency_symbol' => config('scheme.symbol'),
            'min_version' => config('mobile.min_version'),
            'force_update' => (bool) config('mobile.force_update'),
            'store_url' => config('mobile.store_url'),
            'download_url' => \Illuminate\Support\Facades\Storage::disk('public')->exists(
                $setting('android_apk_path', 'releases/scheme-erp.apk')
            ) ? route('app.download') : null,
            'support_phone' => config('mobile.support_phone')
                ?: $setting('support_phone') ?: $setting('company_phone'),
            'support_whatsapp' => config('mobile.support_whatsapp')
                ?: $setting('support_whatsapp'),
            'logo' => $setting('logo') ? storage_url($setting('logo')) : null,
            'features' => [
                // Only offered when an SMS gateway can really deliver, and not
                // switched off by hand in Settings.
                'otp_login' => app(\App\Services\SmsService::class)->configured()
                    && $setting('otp_login', '1') !== '0',
                'self_register' => true,
                'agent_login' => true,
                'online_payment' => $razorpay->configured(),
                'manual_payment' => false,
                'points' => true,
                'referral' => true,
            ],
            'payment' => [
                'gateway' => 'razorpay',
                'gateway_enabled' => $razorpay->configured(),
                'razorpay_key' => $razorpay->keyId(),
                'theme_color' => config('mobile.payment.razorpay.theme_color'),
                'upi_only' => true,
                'live_mode' => (bool) config('mobile.payment.razorpay.live_mode'),
            ],
            'otp' => [
                'length' => (int) config('mobile.otp.length'),
                'ttl_seconds' => (int) config('mobile.otp.ttl_seconds'),
                'resend_after' => (int) config('mobile.otp.resend_after'),
            ],
        ]);
    }

    /** GET /api/v1/rates */
    public function rates(Request $request)
    {
        $rows = MetalRate::with('metal:id,name,code,unit')
            ->where('status', true)
            ->when($request->metal_id, fn ($q, $v) => $q->where('metal_id', $v))
            ->orderByDesc('rate_date')
            ->limit(60)->get()
            ->map(fn ($r) => [
                'metal' => $r->metal->name,
                'rate' => (float) $r->rate,
                'date' => $r->rate_date->toDateString(),
            ]);

        return $this->ok($rows);
    }

    /** GET /api/v1/schemes */
    public function schemes()
    {
        return $this->ok(Scheme::active()->with('metal:id,name')->get()->map(fn ($s) => $this->schemeArr($s)));
    }

    /** GET /api/v1/products */
    public function products(Request $request)
    {
        $rows = Product::active()->with('category:id,name')
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->paginate($this->perPage($request, 20));

        return $this->ok($rows->through(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category?->name,
            'weight' => (float) $p->weight,
            'price' => (float) $p->price,
            'image' => $p->image ? storage_url($p->image) : null,
            'description' => $p->description,
        ]));
    }

    public function categories()
    {
        return $this->ok(Category::active()->orderBy('sortorder')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'image' => $c->image ? storage_url($c->image) : null,
            ]));
    }

    public function banks()
    {
        return $this->ok(Bank::active()->get()
            ->map(fn ($b) => [
                'bank_name' => $b->bank_name,
                'beneficiary_name' => $b->beneficiary_name,
                'ifsc' => $b->ifsc,
                'account_no' => $b->account_no,
                'branch' => $b->branch,
                'upi_id' => $b->upi_id,
                'qr_image' => $b->qr_image ? storage_url($b->qr_image) : null,
            ]));
    }

    public function content(string $type = 'TERMS')
    {
        if (strtoupper($type) === 'ABOUT') {
            $a = About::first();

            return $this->ok($a ? [
                'title' => $a->title,
                'description' => $a->description,
                'image' => $a->image ? storage_url($a->image) : null,
            ] : null);
        }

        return $this->ok(Term::active()->where('type', strtoupper($type))->get(['title', 'description']));
    }

    /* =====================================================================
     |  AUTHENTICATED (customer token)
     ===================================================================== */

    /** GET /api/v1/my/schemes */
    public function mySchemes(Request $request)
    {
        $rows = CustomerScheme::with('scheme:id,name,code')
            ->where('customer_id', $request->user()->id)
            ->when($request->status, fn ($q, $v) => $q->where('status', strtoupper($v)))
            ->latest('id')->get()
            ->map(fn ($cs) => [
                'id' => $cs->id,
                'account_no' => $cs->account_no,
                'scheme' => $cs->scheme->name,
                'installment_amount' => (float) $cs->installment_amount,
                'paid_installments' => $cs->paid_installments,
                'total_installments' => $cs->total_installments,
                'pending_installments' => $cs->pending_installments,
                'progress' => $cs->progress,
                'total_paid' => (float) $cs->total_paid,
                'total_gram' => (float) $cs->total_gram,
                'start_date' => $cs->start_date?->toDateString(),
                'maturity_date' => $cs->maturity_date?->toDateString(),
                'status' => $cs->status,
            ]);

        return $this->ok($rows);
    }

    /** GET /api/v1/my/schemes/{id} */
    public function schemeDetail(Request $request, int $id)
    {
        // CustomerScheme::installments() is already ordered by installment_no.
        $cs = CustomerScheme::with(['scheme', 'installments', 'payments'])
            ->where('customer_id', $request->user()->id)
            ->findOrFail($id);

        return $this->ok([
            // The app builds its scheme model from this payload, so it has to
            // carry the same fields the list at /my/schemes does.
            'id' => $cs->id,
            'account_no' => $cs->account_no,
            'scheme' => $cs->scheme->name,
            'status' => $cs->status,
            'installment_amount' => (float) $cs->installment_amount,
            'paid_installments' => $cs->paid_installments,
            'total_installments' => $cs->total_installments,
            'pending_installments' => $cs->pending_installments,
            'total_paid' => (float) $cs->total_paid,
            'total_gram' => (float) $cs->total_gram,
            'progress' => $cs->progress,
            'start_date' => $cs->start_date?->toDateString(),
            'maturity_date' => $cs->maturity_date?->toDateString(),
            'installments' => $cs->installments->map(fn ($i) => [
                'no' => $i->installment_no,
                'due_date' => $i->due_date->toDateString(),
                'amount' => (float) $i->amount,
                'paid_amount' => (float) $i->paid_amount,
                'gram' => (float) $i->gram,
                'status' => $i->status,
            ]),
            'payments' => $cs->payments->map(fn ($p) => $this->paymentArr($p)),
        ]);
    }

    /** POST /api/v1/my/schemes/join */
    public function join(Request $request)
    {
        $data = $request->validate([
            'scheme_id' => ['required', 'exists:schemes,id'],
            'installment_amount' => ['nullable', 'numeric', 'min:1'],
            'start_date' => ['nullable', 'date'],
        ]);

        $cs = $this->service->enroll(
            $request->user(),
            Scheme::findOrFail($data['scheme_id']),
            $data + [
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'source' => 'APP',
            ]
        );

        return $this->ok([
            'id' => $cs->id,
            'account_no' => $cs->account_no,
            'total_installments' => $cs->total_installments,
            'maturity_date' => $cs->maturity_date?->toDateString(),
        ], 'Scheme joined successfully');
    }

    /** POST /api/v1/my/payments  — customer submits a payment (goes to PENDING) */
    public function pay(Request $request)
    {
        $data = $request->validate([
            'customer_scheme_id' => ['nullable', 'exists:customer_schemes,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_mode' => ['required', 'in:UPI,CARD,BANK,CASH,WALLET'],
            'txn_ref' => ['nullable', 'string', 'max:80'],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        // Never let a customer pay into somebody else's scheme.
        if (! empty($data['customer_scheme_id'])) {
            $owns = CustomerScheme::where('id', $data['customer_scheme_id'])
                ->where('customer_id', $request->user()->id)
                ->exists();

            if (! $owns) {
                return response()->json([
                    'success' => false,
                    'message' => 'That scheme does not belong to you.',
                ], 403);
            }
        }

        if ($request->hasFile('screenshot')) {
            $data['screenshot'] = $request->file('screenshot')->store('payments', 'public');
        }

        $data['customer_id'] = $request->user()->id;
        $data['payment_date'] = now()->toDateString();
        // customer_scheme_id is optional: a direct payment omits the key entirely.
        $data['payment_type'] = empty($data['customer_scheme_id']) ? 'DIRECT' : 'INSTALLMENT';
        $data['status'] = 'PENDING';   // admin approve cheythal mathrame accounts-il varu

        $payment = $this->service->recordPayment($data);

        return $this->ok($this->paymentArr($payment), 'Payment submitted. Waiting for approval.');
    }

    /** GET /api/v1/my/payments */
    public function myPayments(Request $request)
    {
        $mine = fn () => Payment::where('customer_id', $request->user()->id);

        $rows = $mine()
            ->when($request->status, fn ($q, $v) => $q->where('status', strtoupper($v)))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($this->perPage($request));

        $payload = $rows->through(fn ($p) => $this->paymentArr($p))->toArray();

        // Lifetime figures for the header. The app pages through this list, so
        // it cannot add them up from the rows it happens to be holding.
        $payload['summary'] = [
            'count' => $mine()->count(),
            'approved' => (float) $mine()->where('status', 'APPROVED')->sum('total'),
            'pending' => (float) $mine()->where('status', 'PENDING')->sum('total'),
        ];

        return $this->ok($payload);
    }

    /** GET /api/v1/my/wallet */
    public function wallet(Request $request)
    {
        $c = $request->user()->load('point');
        $privilege = app(\App\Services\PrivilegeService::class)->summary($c);

        return $this->ok([
            'wallet_amount' => (float) $c->wallet_amount,
            'total_gram' => (float) $c->total_gram,
            'points_balance' => (float) ($c->point->balance_point ?? 0),
            'points_redeemed' => (float) ($c->point->redeemed_point ?? 0),
            'points_total' => (float) ($c->point->total_point ?? 0),
            'privilege' => $privilege,
            'gold_rate' => MetalRate::latest_rate(),
            'gold_value' => round((float) $c->total_gram * MetalRate::latest_rate(), 2),
        ]);
    }

    /** GET /api/v1/my/points */
    public function points(Request $request)
    {
        $rows = $request->user()->pointTransactions()
            ->orderByDesc('txn_date')->limit(100)->get()
            ->map(fn ($t) => [
                'type' => $t->type,
                'points' => (float) $t->points,
                'amount' => (float) $t->amount,
                'gram' => (float) $t->gram,
                'source' => $t->source,
                'date' => $t->txn_date->toDateString(),
                'note' => $t->note,
            ]);

        return $this->ok($rows);
    }

    /** POST /api/v1/my/points/redeem */
    public function redeemPoints(Request $request)
    {
        $data = $request->validate([
            'points' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $t = $this->service->redeemPoints($request->user(), (float) $data['points'], $data);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->ok(['points' => (float) $t->points], 'Points redeem request submitted');
    }

    /** GET /api/v1/my/passbook — ledger statement from daybook */
    public function passbook(Request $request)
    {
        $customer = $request->user();

        if (! $customer->accode) {
            return $this->ok(['opening' => 0, 'lines' => []]);
        }

        $data = app(\App\Services\FinancialReportService::class)->generalLedger(
            $customer->accode,
            $request->from ?: now()->subYear()->toDateString(),
            $request->to ?: now()->toDateString()
        );

        return $this->ok([
            'opening' => $data['opening'],
            'closing' => $data['closing'],
            'closing_side' => $data['closing_side'],
            'lines' => collect($data['lines'])->map(fn ($l) => [
                'date' => $l['date'],
                'particulars' => $l['particulars'],
                'voucher_no' => $l['voucher_no'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
                'balance' => $l['balance'],
            ]),
        ]);
    }

    /* =====================================================================
     |  helpers
     ===================================================================== */

    protected function schemeArr(Scheme $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'code' => $s->code,
            'amount' => (float) $s->amount,
            'min_amount' => (float) $s->min_amount,
            'max_amount' => (float) $s->max_amount,
            'flexible_amount' => (bool) $s->flexible_amount,
            'payment_interval' => $s->payment_interval,
            'total_installments' => $s->total_installments,
            'duration_months' => $s->duration_months,
            'bonus_type' => $s->bonus_type,
            'bonus_value' => (float) $s->bonus_value,
            'maturity_value' => $s->maturity_value,
            'gst_percent' => (float) $s->gst_percent,
            'description' => $s->description,
            'image' => $s->image ? storage_url($s->image) : null,
        ];
    }

    protected function paymentArr(Payment $p): array
    {
        return [
            'id' => $p->id,
            'receipt_no' => $p->receipt_no,
            'amount' => (float) $p->amount,
            'tax' => (float) $p->tax,
            'total' => (float) $p->total,
            'rate' => (float) $p->rate,
            'gram' => (float) $p->gram,
            'date' => $p->payment_date->toDateString(),
            'mode' => $p->payment_mode,
            'type' => $p->payment_type,
            'status' => $p->status,
            'txn_ref' => $p->txn_ref,
            'screenshot' => $p->screenshot ? storage_url($p->screenshot) : null,
        ];
    }

    protected function ok($data, string $message = 'OK')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
