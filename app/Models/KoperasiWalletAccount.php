<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiWalletAccount extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    public const TYPE_CAPITAL = 'modal';
    public const TYPE_HOLDING = 'penampungan';
    public const TYPE_INCOME = 'pendapatan';
    public const TYPE_LENDING = 'pinjaman';
    public const TYPE_OPERATIONAL = 'operasional';
    public const TYPE_RESERVE = 'cadangan';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'user_id',
        'name',
        'wallet_type',
        'opening_balance',
        'is_active',
        'description',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'is_active' => 'boolean',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_CAPITAL => 'Modal',
            self::TYPE_HOLDING => 'Penampungan',
            self::TYPE_INCOME => 'Pendapatan',
            self::TYPE_LENDING => 'Pinjaman',
            self::TYPE_OPERATIONAL => 'Operasional',
            self::TYPE_RESERVE => 'Cadangan',
            self::TYPE_CUSTOM => 'Custom',
        ];
    }

    public static function defaultDefinitions(): array
    {
        return [
            [
                'name' => 'Modal Utama',
                'wallet_type' => self::TYPE_CAPITAL,
                'opening_balance' => 0,
                'description' => 'Akun modal awal koperasi.',
            ],
            [
                'name' => 'Penampungan Simpanan',
                'wallet_type' => self::TYPE_HOLDING,
                'opening_balance' => 0,
                'description' => 'Dompet penampungan simpanan anggota.',
            ],
            [
                'name' => 'Kas Pinjaman',
                'wallet_type' => self::TYPE_LENDING,
                'opening_balance' => 0,
                'description' => 'Dompet untuk pencairan dan pengembalian pokok pinjaman.',
            ],
            [
                'name' => 'Dompet Pendapatan',
                'wallet_type' => self::TYPE_INCOME,
                'opening_balance' => 0,
                'description' => 'Dompet untuk jasa pinjaman, admin, dan denda.',
            ],
        ];
    }

    public function savings()
    {
        return $this->hasMany(KoperasiSaving::class, 'wallet_account_id');
    }

    public function loans()
    {
        return $this->hasMany(KoperasiLoan::class, 'wallet_account_id');
    }

    public function installmentPrincipalEntries()
    {
        return $this->hasMany(KoperasiLoanInstallment::class, 'principal_wallet_account_id');
    }

    public function installmentIncomeEntries()
    {
        return $this->hasMany(KoperasiLoanInstallment::class, 'income_wallet_account_id');
    }
}
