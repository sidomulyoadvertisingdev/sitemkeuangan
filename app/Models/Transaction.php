<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    protected $fillable = [
        'user_id',
        'type',
        'category_id',
        'project_id',
        'bank_account_id',
        'amount',
        'date',
        'note',
    ];

    /**
     * RELASI KE CATEGORY
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
    
}
