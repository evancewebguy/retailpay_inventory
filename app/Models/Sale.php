<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sale_number',
        'store_id',
        'customer_id',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'payment_status',
        'status',
        'created_by'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            $sale->sale_number = static::generateSaleNumber();
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    protected static function generateSaleNumber()
    {
        $prefix = 'SAL';
        $date = now()->format('Ymd');
        $lastSale = static::whereDate('created_at', today())->count();
        
        return $prefix . '-' . $date . '-' . str_pad($lastSale + 1, 4, '0', STR_PAD_LEFT);
    }

}
