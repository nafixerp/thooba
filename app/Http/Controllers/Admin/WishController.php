<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\Request;

class WishController extends Controller
{
    public function index()
    {
        $customers = Customer::active()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'reg_no']);

        $wishes = Notification::with('notifiable:id,name,phone')
            ->where('notifiable_type', Customer::class)
            ->where('data->kind', 'wish')
            ->latest('id')
            ->paginate(25);

        return view('admin.wishes.index', compact('customers', 'wishes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required'],
            'occasion' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        if ($data['customer_id'] !== 'all') {
            $request->validate(['customer_id' => ['exists:customers,id']]);
        }

        $company = (string) Setting::get('company_name', config('app.name', 'Scheme ERP'));
        $payload = [
            'kind' => 'wish',
            'sender_name' => $company,
            'occasion' => $data['occasion'],
        ];
        $count = 0;

        $create = function (Customer $customer) use ($data, $payload, &$count): void {
            Notification::create([
                'notifiable_type' => Customer::class,
                'notifiable_id' => $customer->id,
                'title' => $data['occasion'],
                'message' => $data['message'],
                'type' => 'BIRTHDAY',
                'data' => $payload,
                'sent_at' => now(),
            ]);
            $count++;
        };

        if ($data['customer_id'] === 'all') {
            Customer::active()->select('id')->chunkById(250, function ($customers) use ($create): void {
                $customers->each($create);
            });
        } else {
            $create(Customer::active()->findOrFail((int) $data['customer_id']));
        }

        return back()->with('success', "Wish sent from {$company} to {$count} customer(s).");
    }
}
