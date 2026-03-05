<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'address', 'phone', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
