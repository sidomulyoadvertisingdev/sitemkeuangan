<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'name',
        'description',
        'target_amount',
        'start_date',
        'end_date',
        'status',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(ProjectTransaction::class);
    }

    public function expenses()
    {
        return $this->transactions()->where('type', 'expense');
    }

    public function allocations()
    {
        return $this->transactions()->where('type', 'allocation');
    }
}
