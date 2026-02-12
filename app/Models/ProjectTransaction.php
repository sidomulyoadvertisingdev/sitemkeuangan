<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bank_account_id',
        'category_id',
        'type',
        'amount',
        'date',
        'note',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
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
