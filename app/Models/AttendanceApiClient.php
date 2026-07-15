<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceApiClient extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allowsIp(?string $ip): bool
    {
        $allowedIps = array_values(array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))));
        if ($allowedIps === []) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        return in_array($ip, $allowedIps, true);
    }
}
