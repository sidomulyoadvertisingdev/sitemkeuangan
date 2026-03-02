<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    public const KIND_GENERAL = 'general';
    public const KIND_OFFICER_WALLET = 'officer_wallet';

    protected $fillable = [
        'user_id',
        'account_kind',
        'owner_user_id',
        'name',
        'bank_name',
        'account_number',
        'balance',
        'is_default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function projectTransactions()
    {
        return $this->hasMany(ProjectTransaction::class);
    }
}
