<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestmentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'investment_asset_id',
        'amount_fiat',
        'price_fiat',
        'quantity',
        'currency',
        'executed_at',
        'note',
    ];

    protected $dates = ['executed_at'];

    public function asset()
    {
        return $this->belongsTo(InvestmentAsset::class, 'investment_asset_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
