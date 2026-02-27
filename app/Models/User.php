<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const MODE_ORGANIZATION = 'organization';
    public const MODE_COOPERATIVE = 'cooperative';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_BANNED = 'banned';

    public const PERMISSIONS = [
        'transactions.manage' => 'Kelola Transaksi',
        'bank_accounts.manage' => 'Kelola Rekening Bank',
        'projects.manage' => 'Kelola Proyek',
        'investments.manage' => 'Kelola Investasi',
        'budgets.manage' => 'Kelola Budget',
        'debts.manage' => 'Kelola Hutang & Piutang',
        'iuran.manage' => 'Kelola Iuran',
        'iuran.import' => 'Import / Export Iuran',
        'koperasi.manage' => 'Kelola Koperasi Simpan Pinjam',
        'reports.view' => 'Lihat Laporan Lengkap',
        'users.manage' => 'Kontrol Pengguna',
    ];

    protected $fillable = [
        'name',
        'organization_name',
        'account_mode',
        'email',
        'google_id',
        'google_linked_at',
        'password',
        'is_admin',
        'is_platform_admin',
        'permissions',
        'account_status',
        'approved_at',
        'approved_by',
        'data_owner_user_id',
        'invite_quota',
        'banned_at',
        'banned_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_platform_admin' => 'boolean',
        'permissions' => 'array',
        'invite_quota' => 'integer',
        'google_linked_at' => 'datetime',
        'approved_at' => 'datetime',
        'banned_at' => 'datetime',
    ];

    public static function permissionOptions(): array
    {
        return self::PERMISSIONS;
    }

    public static function modeOptions(): array
    {
        return [
            self::MODE_ORGANIZATION => 'Organizational Finance',
            self::MODE_COOPERATIVE => 'Cooperative Finance',
        ];
    }

    public function isOrganizationMode(): bool
    {
        return $this->account_mode === self::MODE_ORGANIZATION;
    }

    public function isCooperativeMode(): bool
    {
        return $this->account_mode === self::MODE_COOPERATIVE;
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        if ($this->is_platform_admin) {
            return true;
        }

        if ($permission === 'users.manage') {
            return $this->is_admin;
        }

        if ($this->is_admin) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions, true);
    }

    public function isApproved(): bool
    {
        return $this->account_status === self::STATUS_APPROVED;
    }

    public function isPendingApproval(): bool
    {
        return $this->account_status === self::STATUS_PENDING;
    }

    public function isBanned(): bool
    {
        return $this->account_status === self::STATUS_BANNED;
    }

    public function tenantUserId(): int
    {
        return (int) ($this->data_owner_user_id ?: $this->id);
    }

    public function dataOwner()
    {
        return $this->belongsTo(User::class, 'data_owner_user_id');
    }
}
