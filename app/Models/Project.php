<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    public const IURAN_MODE_DEFAULT = 'default';
    public const IURAN_MODE_KELAS = 'kelas';

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'name',
        'description',
        'target_amount',
        'iuran_allocation_mode',
        'iuran_class_a_percent',
        'iuran_class_b_percent',
        'iuran_class_c_percent',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'target_amount' => 'float',
        'iuran_class_a_percent' => 'float',
        'iuran_class_b_percent' => 'float',
        'iuran_class_c_percent' => 'float',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(ProjectTransaction::class);
    }

    public function expenses()
    {
        return $this->transactions()->where('type', 'expense');
    }

    public function allocations()
    {
        return $this->transactions()->where('type', 'allocation');
    }

    public function iuranAssignments()
    {
        return $this->hasMany(ProjectIuranAssignment::class);
    }

    public function iuranMembers()
    {
        return $this->belongsToMany(IuranMember::class, 'project_iuran_assignments', 'project_id', 'iuran_member_id')
            ->withPivot([
                'officer_user_id',
                'assigned_by',
                'note',
                'allocation_mode',
                'member_class',
                'class_percent',
                'planned_amount',
            ])
            ->withTimestamps();
    }

    public function classPercent(string $memberClass): float
    {
        return match (strtoupper($memberClass)) {
            'A' => max(1, (float) $this->iuran_class_a_percent),
            'B' => max(1, (float) $this->iuran_class_b_percent),
            default => max(1, (float) $this->iuran_class_c_percent),
        };
    }
}
