<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiLoanInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'koperasi_loan_id',
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
}
