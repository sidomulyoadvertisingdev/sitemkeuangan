<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToCurrentUser;

    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];
}
