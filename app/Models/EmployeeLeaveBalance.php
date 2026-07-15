<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'allocated' => 'decimal:2',
            'carried_forward' => 'decimal:2',
            'earned_credited' => 'decimal:2',
            'availed' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveCategory(): BelongsTo
    {
        return $this->belongsTo(LeaveCategory::class);
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }
}
