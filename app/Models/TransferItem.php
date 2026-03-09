<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transfer_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_id',
        'product_id',
        'quantity_requested',
        'quantity_shipped',
        'quantity_received',
        'status',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_shipped' => 'integer',
        'quantity_received' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the transfer that owns this item.
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Get the product for this transfer item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Check if item is fully shipped.
     */
    public function isFullyShipped(): bool
    {
        return $this->quantity_shipped !== null && 
               $this->quantity_shipped >= $this->quantity_requested;
    }

    /**
     * Check if item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received !== null && 
               $this->quantity_received >= $this->quantity_requested;
    }

    /**
     * Get the remaining quantity to ship.
     */
    public function getRemainingToShipAttribute(): int
    {
        return $this->quantity_requested - ($this->quantity_shipped ?? 0);
    }

    /**
     * Get the remaining quantity to receive.
     */
    public function getRemainingToReceiveAttribute(): int
    {
        return ($this->quantity_shipped ?? 0) - ($this->quantity_received ?? 0);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'PENDING' => 'yellow',
            'SHIPPED' => 'blue',
            'RECEIVED' => 'green',
            'PARTIAL' => 'orange',
            default => 'gray'
        };
    }

    /**
     * Scope a query to only include items with a specific status.
     */
    public function scopeWhereStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending items.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope a query to only include shipped items.
     */
    public function scopeShipped($query)
    {
        return $query->where('status', 'SHIPPED');
    }

    /**
     * Scope a query to only include received items.
     */
    public function scopeReceived($query)
    {
        return $query->where('status', 'RECEIVED');
    }
}