<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'adjustment_number',
        'store_id',
        'type',
        'reason',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the store that owns the adjustment.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user who created the adjustment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the adjustment.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the items for this adjustment.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AdjustmentItem::class, 'stock_adjustment_id');
    }

    /**
     * Generate a unique adjustment number.
     */
    public static function generateAdjustmentNumber(): string
    {
        $prefix = 'ADJ';
        $date = now()->format('Ymd');
        $lastAdjustment = self::whereDate('created_at', today())->count();
        
        return $prefix . '-' . $date . '-' . str_pad($lastAdjustment + 1, 4, '0', STR_PAD_LEFT);
    }
}