<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemSetting::query()
            ->where('key', 'time_zone')
            ->where('value', 'UTC')
            ->update(['value' => 'Asia/Kathmandu']);

        SystemSetting::forgetCache();
    }

    public function down(): void
    {
        SystemSetting::query()
            ->where('key', 'time_zone')
            ->where('value', 'Asia/Kathmandu')
            ->update(['value' => 'UTC']);

        SystemSetting::forgetCache();
    }
};
