<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeResignationRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function supervisorActionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_action_by');
    }

    public function finalActionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_action_by');
    }
}
