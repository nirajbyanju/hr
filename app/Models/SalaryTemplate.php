<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_salary_templates')
            ->withPivot([
                'pay_frequency',
                'basic_salary',
                'house_rent',
                'medical_allowance',
                'conveyance_allowance',
                'other_allowance',
                'gross_salary',
                'provident_fund_percent',
                'tax_percent',
                'ctc_amount',
                'notes',
                'effective_from',
                'effective_to',
            ])
            ->withTimestamps();
    }
}
