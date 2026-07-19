<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Tenancy\BelongsToTenant;

class Holiday extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_optional' => 'boolean',
        ];
    }
}
