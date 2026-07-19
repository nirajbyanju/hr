<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdCardPrintLog extends Model
{
    protected $fillable = [
        'employee_id_card_id',
        'employee_id',
        'event',
        'format',
        'performed_by',
        'ip_address',
        'user_agent',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCard::class, 'employee_id_card_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
