<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'koperasi_member_id',
        'loan_no',
        'principal_amount',
        'interest_percent',
        'admin_fee',
        'tenor_months',
        'disbursed_at',
        'due_date',
        'status',
        'note',
    ];

    protected $casts = [
        'disbursed_at' => 'date',
        'due_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(KoperasiMember::class, 'koperasi_member_id');
    }

    public function installments()
    {
        return $this->hasMany(KoperasiLoanInstallment::class);
    }
}
