<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileTransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'requester_member_id',
        'target_member_id',
        'kind',
        'unique_code',
        'pay_amount',
        'bank_account_id',
        'amount',
        'note',
        'proof_path',
        'proof_submitted_at',
        'status',
        'approved_at',
        'approved_by_user_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'proof_submitted_at' => 'datetime',
    ];

    public function requesterMember()
    {
        return $this->belongsTo(KoperasiMember::class, 'requester_member_id');
    }

    public function targetMember()
    {
        return $this->belongsTo(KoperasiMember::class, 'target_member_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
