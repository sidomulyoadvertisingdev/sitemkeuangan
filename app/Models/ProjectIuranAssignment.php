<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectIuranAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'iuran_member_id',
        'officer_user_id',
        'allocation_mode',
        'member_class',
        'class_percent',
        'planned_amount',
        'assigned_by',
        'note',
    ];

    protected $casts = [
        'class_percent' => 'float',
        'planned_amount' => 'float',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function member()
    {
        return $this->belongsTo(IuranMember::class, 'iuran_member_id');
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
