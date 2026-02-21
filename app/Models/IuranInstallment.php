<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IuranInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'iuran_member_id',
        'bank_account_id',
        'category_id',
        'amount',
        'paid_at',
        'note',
    ];

    public function member()
    {
        return $this->belongsTo(IuranMember::class, 'iuran_member_id');
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
