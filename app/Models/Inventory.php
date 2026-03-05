<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    //
    protected $fillable = [
        'store_id', 'product_id', 'quantity', 
        'reserved_quantity', 'reorder_point', 'max_stock'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'reorder_point' => 'integer',
        'max_stock' => 'integer',
    ];

    protected $appends = ['available_quantity'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function isLowStock()
    {
        return $this->available_quantity <= $this->reorder_point;
    }

    public function isOverstock()
    {
        return $this->max_stock && $this->quantity > $this->max_stock;
    }

}
