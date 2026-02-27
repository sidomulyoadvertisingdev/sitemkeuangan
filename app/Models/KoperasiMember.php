<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiMember extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    protected $fillable = [
        'user_id',
        'member_no',
        'name',
        'nik',
        'gender',
        'phone',
        'address',
        'join_date',
        'status',
        'note',
    ];

    protected $casts = [
        'join_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            if (empty($member->member_no)) {
                $member->member_no = self::generateUniqueAccountNumber();
            }
        });
    }

    public static function generateUniqueAccountNumber(int $maxAttempts = 200): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = (string) random_int(10000000, 99999999);
            $exists = self::query()->where('member_no', $candidate)->exists();
            if (!$exists) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan nomor rekening unik.');
    }

    public function savings()
    {
        return $this->hasMany(KoperasiSaving::class);
    }

    public function loans()
    {
        return $this->hasMany(KoperasiLoan::class);
    }
}
