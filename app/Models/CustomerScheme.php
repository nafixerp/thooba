<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerScheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_no', 'book_no', 'source', 'customer_id', 'scheme_id', 'agent_id',
        'installment_amount', 'total_installments', 'paid_installments',
        'payment_interval', 'start_date', 'end_date', 'maturity_date',
        'maturity_notified_at', 'maturity_notification_note',
        'total_paid', 'total_gst', 'total_gram', 'bonus_amount', 'bonus_gram',
        'status', 'closed_date', 'closure_type', 'closure_amount', 'closure_mode',
        'closure_daybook_sno', 'renewed_from_id', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'maturity_date' => 'date',
            'maturity_notified_at' => 'datetime',
            'closed_date' => 'date',
            'closure_amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'total_gst' => 'decimal:2',
            'total_gram' => 'decimal:3',
            'bonus_amount' => 'decimal:2',
            'bonus_gram' => 'decimal:3',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function scheme(): BelongsTo { return $this->belongsTo(Scheme::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }

    /**
     * The payment schedule, always in installment order.
     *
     * Without the order the storage engine decides, and updating a row moved
     * it: a customer's schedule came back 4,5..12,1,2,3 once the first three
     * were paid.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_no');
    }

    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function closures(): HasMany { return $this->hasMany(SchemeClosure::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }

    public function scopeActive($q) { return $q->where('status', 'ACTIVE'); }

    public function getPendingInstallmentsAttribute(): int
    {
        return max(0, $this->total_installments - $this->paid_installments);
    }

    public function getProgressAttribute(): float
    {
        return $this->total_installments
            ? round($this->paid_installments / $this->total_installments * 100, 1)
            : 0;
    }

    public function getIsMaturedAttribute(): bool
    {
        return $this->paid_installments >= $this->total_installments
            || ($this->maturity_date && $this->maturity_date->isPast());
    }

    /** Add N intervals to a date based on payment_interval */
    public function nextDue(Carbon $from, int $n = 1): Carbon
    {
        return match ($this->payment_interval) {
            'DAILY' => $from->copy()->addDays($n),
            'WEEKLY' => $from->copy()->addWeeks($n),
            'QUARTERLY' => $from->copy()->addMonths(3 * $n),
            'YEARLY' => $from->copy()->addYears($n),
            default => $from->copy()->addMonths($n),
        };
    }

    public static function nextAccountNo(): string
    {
        $prefix = \App\Models\Setting::get('scheme_account_prefix', 'SCH');

        return $prefix.date('y').str_pad((string) ((int) static::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * The next passbook number to propose.
     *
     * Read off the highest book number actually in use rather than the row
     * count: books get closed and rows get deleted, and counting rows would
     * hand out a number somebody is already holding.
     *
     * Only plain numeric books take part. A shop that writes "A-142" has its
     * own series, and those are skipped rather than guessed at.
     */
    public static function nextBookNo(): string
    {
        $numbers = static::query()
            ->whereNotNull('book_no')
            ->pluck('book_no')
            ->filter(fn ($b) => ctype_digit(trim((string) $b)))
            ->map(fn ($b) => (int) $b);

        return (string) (($numbers->max() ?? 0) + 1);
    }
}
