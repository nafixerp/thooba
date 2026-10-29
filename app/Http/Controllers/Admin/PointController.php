<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\Setting;
use App\Services\SchemeService;
use Illuminate\Http\Request;

class PointController extends Controller
{
    public function __construct(protected SchemeService $service) {}

    public function index(Request $request)
    {
        $points = Point::with('customer:id,name,phone,reg_no,referral_code')
            ->when($request->q, fn ($q, $v) => $q->whereHas('customer',
                fn ($c) => $c->where('name', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")))
            ->orderByDesc('balance_point')
            ->paginate(25)->withQueryString();

        return view('admin.points.index', compact('points'));
    }

    public function redeemReport(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $rows = PointTransaction::with('customer:id,name,phone')
            ->where('type', 'REDEEM')
            ->whereBetween('txn_date', [$from, $to])
            ->latest('txn_date')->paginate(25)->withQueryString();

        return view('admin.points.redeem', compact('rows', 'from', 'to'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'points' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        $this->service->addPoints(Customer::find($data['customer_id']), (float) $data['points'], 'MANUAL', $data['note'] ?? null);

        return back()->with('success', 'Privilege points added.');
    }

    public function redeem(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'points' => ['required', 'numeric', 'min:1'],
            'amount' => ['nullable', 'numeric'],
            'gram' => ['nullable', 'numeric'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $this->service->redeemPoints(Customer::find($data['customer_id']), (float) $data['points'], $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Points redeemed.');
    }

    public function referrals(Request $request)
    {
        $rows = Referral::with(['customer:id,name,phone', 'referred:id,name,phone'])
            ->latest('id')->paginate(25);

        $defaultPoints = Setting::get('referral_points', 100);

        return view('admin.points.referrals', compact('rows', 'defaultPoints'));
    }

    public function saveReferralSetting(Request $request)
    {
        $request->validate(['referral_points' => ['required', 'numeric', 'min:0']]);
        Setting::put('referral_points', $request->referral_points, 'points');

        return back()->with('success', 'Default referral points saved.');
    }
}
