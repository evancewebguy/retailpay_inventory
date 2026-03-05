<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'reference_number',
        'movement_type',
        'product_id',
        'from_store_id',
        'to_store_id',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reason',
        'notes',
        'reference_type',
        'reference_id',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($movement) {
            $movement->reference_number = static::generateReferenceNumber($movement->movement_type);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    protected static function generateReferenceNumber($type)
    {
        $prefix = match($type) {
            'SALE' => 'SAL',
            'TRANSFER' => 'TRF',
            'ADJUSTMENT' => 'ADJ',
            'PROCUREMENT' => 'PRO',
            'RETURN' => 'RET',
            default => 'MOV'
        };

        $date = now()->format('Ymd');
        $lastMovement = static::whereDate('created_at', today())
            ->where('movement_type', $type)
            ->count();

        return $prefix . '-' . $date . '-' . str_pad($lastMovement + 1, 4, '0', STR_PAD_LEFT);
    }
}
