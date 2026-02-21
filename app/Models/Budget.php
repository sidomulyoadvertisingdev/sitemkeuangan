<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BelongsToCurrentUser;

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
