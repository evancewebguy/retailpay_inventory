<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdjustmentItem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'adjustment_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'previous_quantity',
        'adjusted_quantity',
        'new_quantity',
        'reason'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'previous_quantity' => 'integer',
        'adjusted_quantity' => 'integer',
        'new_quantity' => 'integer',
    ];

    /**
     * Get the adjustment that owns the item.
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    /**
     * Get the product for this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}