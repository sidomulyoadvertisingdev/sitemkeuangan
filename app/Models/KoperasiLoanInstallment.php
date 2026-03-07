<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiLoanInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'koperasi_loan_id',
        'principal_wallet_account_id',
        'income_wallet_account_id',
        'installment_no',
        'expected_amount',
        'amount_principal',
        'amount_interest',
        'amount_penalty',
        'payment_status',
        'shortfall_amount',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'paid_at' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(KoperasiLoan::class, 'koperasi_loan_id');
    }

    public function principalWalletAccount()
    {
        return $this->belongsTo(KoperasiWalletAccount::class, 'principal_wallet_account_id');
    }

    public function incomeWalletAccount()
    {
        return $this->belongsTo(KoperasiWalletAccount::class, 'income_wallet_account_id');
    }
}
