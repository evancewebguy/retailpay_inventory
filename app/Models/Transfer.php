<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Transfer extends Model
{
    use SoftDeletes;

     protected $fillable = [
        'transfer_number',
        'from_store_id',
        'to_store_id',
        'status',
        'expected_delivery_date',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'received_by',
        'received_at'
    ];

    protected $casts = [
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            $transfer->transfer_number = static::generateTransferNumber();
        });
    }

    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    protected static function generateTransferNumber()
    {
        $prefix = 'TRF';
        $date = now()->format('Ymd');
        $lastTransfer = static::whereDate('created_at', today())->count();
        
        return $prefix . '-' . $date . '-' . str_pad($lastTransfer + 1, 4, '0', STR_PAD_LEFT);
    }
}
