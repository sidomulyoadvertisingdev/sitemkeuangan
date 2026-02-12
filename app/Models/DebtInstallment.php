<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'debt_id',
        'bank_account_id',
        'category_id',
        'amount',
        'paid_at',
        'note',
    ];

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
