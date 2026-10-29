<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Closing stock value on a date.
 *
 * dua soft computes this from its inventory tables. This project has no
 * inventory module, so the figure is entered per period - the legacy
 * "Manualstkvalue" mode - and the Trading A/c picks up the most recent value
 * on or before the period end.
 */
class StockValue extends Model
{
    protected $table = 'stock_values';

    protected $fillable = ['as_on', 'amount', 'gram', 'note', 'created_by'];

    protected $casts = [
        'as_on' => 'date',
        'amount' => 'decimal:2',
        'gram' => 'decimal:3',
    ];

    /** Latest valuation on or before $date, or null when none was entered. */
    public static function asOn(string $date): ?self
    {
        return static::whereDate('as_on', '<=', $date)
            ->orderByDesc('as_on')
            ->first();
    }

    /** Value only, 0.00 when nothing has been entered yet. */
    public static function valueAsOn(string $date): float
    {
        return (float) (static::asOn($date)?->amount ?? 0);
    }
}
