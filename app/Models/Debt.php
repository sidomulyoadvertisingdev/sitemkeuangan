<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'name',
        'amount',
        'status',
        'due_date',
        'note',
    ];

    public function installments()
    {
        return $this->hasMany(DebtInstallment::class);
    }

    public function getRemainingAttribute(): float
    {
        $paid = $this->installments()->sum('amount');
        return max(0, $this->amount - $paid);
    }
}
