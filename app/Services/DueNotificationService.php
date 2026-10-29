<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Notification;
use App\Models\Setting;

class DueNotificationService
{
    /** Create one in-app reminder per unpaid installment. Safe to run repeatedly. */
    public function generate(?int $days = null): int
    {
        $days ??= max(0, (int) Setting::get('due_reminder_days', 3));
        $created = 0;

        Installment::with(['customerScheme.customer', 'customerScheme.scheme'])
            ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($installments) use (&$created) {
                foreach ($installments as $installment) {
                    $customer = $installment->customerScheme?->customer;
                    if (! $customer || ! $customer->status) {
                        continue;
                    }

                    $balance = max(0, round((float) $installment->amount - (float) $installment->paid_amount, 2));
                    $scheme = $installment->customerScheme?->scheme?->name ?? 'Scheme';
                    $due = $installment->due_date->format('d M Y');
                    $key = 'installment-due-'.$installment->id.'-'.$installment->due_date->toDateString();

                    $notification = Notification::firstOrCreate(
                        ['dedupe_key' => $key],
                        [
                            'notifiable_type' => get_class($customer),
                            'notifiable_id' => $customer->id,
                            'title' => $installment->due_date->isPast() ? 'Installment overdue' : 'Installment due soon',
                            'message' => "{$scheme} installment #{$installment->installment_no} of Rs ".number_format($balance, 2)." is due on {$due}.",
                            'type' => 'REMINDER',
                            'data' => [
                                'installment_id' => $installment->id,
                                'customer_scheme_id' => $installment->customer_scheme_id,
                                'due_date' => $installment->due_date->toDateString(),
                                'balance' => $balance,
                            ],
                            'sent_at' => now(),
                        ]
                    );

                    if ($notification->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
