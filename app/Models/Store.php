<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Store extends Model
{
    use SoftDeletes;


    protected $fillable = ['branch_id', 'name', 'code', 'location', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(Transfer::class, 'from_store_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(Transfer::class, 'to_store_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

}
