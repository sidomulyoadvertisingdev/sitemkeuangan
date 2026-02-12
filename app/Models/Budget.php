<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'limit',
        'month',
        'year',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
