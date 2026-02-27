<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiSaving extends Model
{
    use HasFactory;

    protected $fillable = [
        'koperasi_member_id',
        'type',
        'amount',
        'transaction_date',
        'note',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(KoperasiMember::class, 'koperasi_member_id');
    }
}
