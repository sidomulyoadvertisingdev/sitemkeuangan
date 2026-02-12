<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestmentAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'symbol',
        'category',
        'market',
        'coingecko_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function allocations()
    {
        return $this->hasMany(InvestmentAllocation::class);
    }
}
