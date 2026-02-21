<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const PERMISSIONS = [
        'transactions.manage' => 'Kelola Transaksi',
        'bank_accounts.manage' => 'Kelola Rekening Bank',
        'projects.manage' => 'Kelola Proyek',
        'investments.manage' => 'Kelola Investasi',
        'budgets.manage' => 'Kelola Budget',
        'debts.manage' => 'Kelola Hutang & Piutang',
        'iuran.manage' => 'Kelola Iuran',
        'iuran.import' => 'Import / Export Iuran',
        'reports.view' => 'Lihat Laporan Lengkap',
        'users.manage' => 'Kelola User & Hak Akses',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'permissions' => 'array',
    ];

    public static function permissionOptions(): array
    {
        return self::PERMISSIONS;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions, true);
    }
}
