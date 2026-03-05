<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'barcode', 'description', 
        'cost_price', 'selling_price', 'unit', 
        'reorder_level', 'is_active'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function transferItems()
    {
        return $this->hasMany(TransferItem::class);
    }
}
