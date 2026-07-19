<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeIdCard extends Model
{
    protected $fillable = [
        'employee_id',
        'card_number',
        'serial',
        'status',
        'generated_at',
        'generated_by',
        'print_count',
        'last_printed_at',
        'last_printed_by',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'last_printed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'print_count' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function lastPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_printed_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(EmployeeIdCardPrintLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->revoked_at === null;
    }

    /**
     * @param Builder<EmployeeIdCard> $query
     * @return Builder<EmployeeIdCard>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('revoked_at');
    }
}
