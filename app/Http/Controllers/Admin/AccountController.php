<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountg;
use App\Models\Accountgbs;
use App\Models\Accountm;
use App\Models\Daybook;
use App\Models\StockValue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /* =========================================================
     |  GROUP BASE  (accountgbs)
     ========================================================= */

    public function bases()
    {
        $bases = Accountgbs::withCount('groups')->orderBy('sortorder')->get();

        return view('accounts.groups.bases', compact('bases'));
    }

    public function storeBase(Request $request, ?string $gbscode = null)
    {
        $data = $request->validate([
            'gbscode' => ['required', 'string', 'max:10', Rule::unique('accountgbs', 'gbscode')->ignore($gbscode, 'gbscode')],
            'name' => ['required', 'string', 'max:100'],
            'nature' => ['required', Rule::in(['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE'])],
            'sortorder' => ['nullable', 'integer'],
        ]);

        $data['bshead'] = in_array($data['nature'], ['ASSET', 'LIABILITY']) ? 'BS' : 'PL';
        $data['normal_side'] = in_array($data['nature'], ['ASSET', 'EXPENSE']) ? 'D' : 'C';
        $data['status'] = $request->boolean('status', true);

        Accountgbs::updateOrCreate(['gbscode' => $gbscode ?? $data['gbscode']], $data);

        return back()->with('success', 'Group base saved.');
    }

    /* =========================================================
     |  GROUP  (accountg)
     ========================================================= */

    public function groups(Request $request)
    {
        $groups = Accountg::with('base', 'parent')
            ->withCount('ledgers')
            ->when($request->gbscode, fn ($q, $v) => $q->where('gbscode', $v))
            ->when($request->q, fn ($q, $v) => $q->where('name', 'like', "%$v%"))
            ->orderBy('sortorder')->orderBy('grcode')
            ->paginate(25)->withQueryString();

        $bases = Accountgbs::active()->orderBy('sortorder')->get();
        $parents = Accountg::active()->orderBy('name')->get();

        return view('accounts.groups.index', compact('groups', 'bases', 'parents'));
    }

    public function storeGroup(Request $request, ?string $grcode = null)
    {
        $data = $request->validate([
            'grcode' => ['required', 'string', 'max:10', Rule::unique('accountg', 'grcode')->ignore($grcode, 'grcode')],
            'name' => ['required', 'string', 'max:120'],
            'gbscode' => ['required', 'exists:accountgbs,gbscode'],
            'parent_grcode' => ['nullable', 'exists:accountg,grcode'],
            'actype' => ['required', Rule::in(['CONTROL', 'NORMAL'])],
            'sortorder' => ['nullable', 'integer'],
        ]);

        $data['bshead'] = Accountgbs::find($data['gbscode'])->bshead;
        $data['status'] = $request->boolean('status', true);

        Accountg::updateOrCreate(['grcode' => $grcode ?? $data['grcode']], $data);

        return redirect()->route('accounts.groups')->with('success', 'Account group saved.');
    }

    public function destroyGroup(string $grcode)
    {
        $group = Accountg::withCount('ledgers')->findOrFail($grcode);

        if ($group->actype === 'CONTROL') {
            return back()->withErrors(['error' => 'System group cannot be deleted.']);
        }
        if ($group->ledgers_count > 0) {
            return back()->withErrors(['error' => 'This group still has ledgers.']);
        }

        $group->delete();

        return back()->with('success', 'Group deleted.');
    }

    /* =========================================================
     |  LEDGER  (accountm)
     ========================================================= */

    public function ledgers(Request $request)
    {
        $ledgers = Accountm::with('group.base')
            ->when($request->grcode, fn ($q, $v) => $q->where('grcode', $v))
            ->when($request->actype, fn ($q, $v) => $q->where('actype', $v))
            ->when($request->bshead, fn ($q, $v) => $q->where('bshead', $v))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$v%")
                ->orWhere('accode', 'like', "%$v%")
                ->orWhere('phone', 'like', "%$v%")))
            ->orderBy('grcode')->orderBy('accode')
            ->paginate(30)->withQueryString();

        // Attach closing balances in one query
        $net = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->whereIn('accode', $ledgers->pluck('accode'))
            ->groupBy('accode')->pluck('net', 'accode');

        $ledgers->each(function ($l) use ($net) {
            $l->closing = round($l->signed_opening + (float) ($net[$l->accode] ?? 0), 2);
        });

        $groups = Accountg::active()->orderBy('name')->get();

        return view('accounts.ledgers.index', compact('ledgers', 'groups'));
    }

    public function createLedger()
    {
        $groups = Accountg::with('base')->active()->orderBy('sortorder')->get();

        return view('accounts.ledgers.form', ['ledger' => new Accountm, 'groups' => $groups]);
    }

    public function editLedger(string $accode)
    {
        $ledger = Accountm::findOrFail($accode);
        $groups = Accountg::with('base')->active()->orderBy('sortorder')->get();

        return view('accounts.ledgers.form', compact('ledger', 'groups'));
    }

    public function storeLedger(Request $request, ?string $accode = null)
    {
        $data = $request->validate([
            'accode' => ['required', 'string', 'max:15', Rule::unique('accountm', 'accode')->ignore($accode, 'accode')],
            'name' => ['required', 'string', 'max:150'],
            'alias' => ['nullable', 'string', 'max:100'],
            'grcode' => ['required', 'exists:accountg,grcode'],
            'actype' => ['required', 'string'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_drcr' => ['required', Rule::in(['D', 'C'])],
            'opening_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'bank_account_no' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['nullable', 'numeric'],
            // Which part of the Trading / P&L statement this ledger closes into.
            'tplpos' => ['nullable', Rule::in([0, 1, 2])],
            'schedule_group' => ['nullable', 'string', 'max:60'],
            'schedule_pos' => ['nullable', 'integer'],
        ]);

        $group = Accountg::find($data['grcode']);
        $data['bshead'] = $group->bshead;
        // Only a P&L ledger has a section; a Balance Sheet ledger never does.
        $data['tplpos'] = $data['bshead'] === 'PL' ? (int) ($data['tplpos'] ?? 0) : 0;
        $data['schedule_group'] = $data['bshead'] === 'BS'
            ? ($data['schedule_group'] ?: $group->name)
            : null;
        $data['schedule_pos'] = (int) ($data['schedule_pos'] ?? 0);
        $data['print_in_reports'] = $request->boolean('print_in_reports', true);
        $data['opening_balance'] = abs((float) ($data['opening_balance'] ?? 0));
        $data['status'] = $request->boolean('status', true);

        Accountm::updateOrCreate(['accode' => $accode ?? $data['accode']], $data);

        return redirect()->route('accounts.ledgers')->with('success', 'Ledger saved.');
    }

    public function destroyLedger(string $accode)
    {
        $ledger = Accountm::findOrFail($accode);

        if ($ledger->is_system) {
            return back()->withErrors(['error' => 'System ledger cannot be deleted.']);
        }
        if (Daybook::where('accode', $accode)->exists()) {
            return back()->withErrors(['error' => 'This ledger already has transactions.']);
        }

        $ledger->delete();

        return back()->with('success', 'Ledger deleted.');
    }

    /* =====================================================================
     |  CLOSING STOCK
     ===================================================================== */

    /**
     * Closing stock valuations.
     *
     * dua soft works this out from its inventory tables. There is no inventory
     * module here, so the figure is entered per date and the Trading A/c and
     * the Balance Sheet both read the latest one on or before their end date.
     */
    public function stockValues()
    {
        $rows = StockValue::orderByDesc('as_on')->paginate(25);
        $ledger = Accountm::find(config('scheme.accounts.stock'));

        return view('accounts.stock-values', compact('rows', 'ledger'));
    }

    public function storeStockValue(Request $request)
    {
        $data = $request->validate([
            'as_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'gram' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        $data['gram'] = (float) ($data['gram'] ?? 0);
        $data['created_by'] = $request->user()?->id;

        StockValue::updateOrCreate(['as_on' => $data['as_on']], $data);

        return back()->with('success', 'Closing stock saved.');
    }

    public function destroyStockValue(int $id)
    {
        StockValue::findOrFail($id)->delete();

        return back()->with('success', 'Closing stock removed.');
    }

    /** Ledger search for the voucher entry screen (JSON). */
    public function search(Request $request)
    {
        return Accountm::active()
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$v%")
                ->orWhere('accode', 'like', "%$v%")))
            ->orderBy('name')
            ->limit(25)
            ->get(['accode', 'name', 'actype', 'bshead']);
    }
}
