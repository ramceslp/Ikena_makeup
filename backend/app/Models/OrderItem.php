<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_title',
        'quantity',
        'unit_price_cents',
        'line_total_cents',
        'unit_cost_cents', // PR4a: cost snapshot at sale time, for margin reporting
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
            'unit_cost_cents'  => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
