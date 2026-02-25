<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTransfer extends Model
{
    use HasFactory;

    public const KIND_DIRECT_TRANSFER = 'direct_transfer';
    public const KIND_PAYMENT_REQUEST = 'payment_request';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sender_user_id',
        'receiver_user_id',
        'sender_bank_account_id',
        'receiver_bank_account_id',
        'kind',
        'status',
        'amount',
        'transfer_date',
        'note',
        'requested_by_user_id',
        'processed_by_user_id',
        'processed_at',
        'rejected_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function senderOrganization()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function receiverOrganization()
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function senderBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'sender_bank_account_id');
    }

    public function receiverBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'receiver_bank_account_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
