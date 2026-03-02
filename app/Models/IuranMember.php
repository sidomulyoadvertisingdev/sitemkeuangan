<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IuranMember extends Model
{
    use HasFactory;
    use BelongsToCurrentUser;

    protected $casts = [
        'target_start_year' => 'integer',
        'target_end_year' => 'integer',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'target_start_year',
        'target_end_year',
        'status',
        'note',
    ];

    public function installments()
    {
        return $this->hasMany(IuranInstallment::class);
    }

    public function projectAssignments()
    {
        return $this->hasMany(ProjectIuranAssignment::class, 'iuran_member_id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_iuran_assignments', 'iuran_member_id', 'project_id')
            ->withPivot(['officer_user_id', 'assigned_by', 'note'])
            ->withTimestamps();
    }

    public function getPaidAttribute(): float
    {
        return (float) $this->installments()->sum('amount');
    }

    public function getRemainingAttribute(): float
    {
        return max(0, (float) $this->target_amount - $this->paid);
    }

    public function getTargetPeriodAttribute(): string
    {
        if ($this->target_start_year === $this->target_end_year) {
            return (string) $this->target_end_year;
        }

        return $this->target_start_year . ' - ' . $this->target_end_year;
    }
}
