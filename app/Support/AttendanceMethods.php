<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * The ways an employee is allowed to mark attendance, as switched on by the
 * administrator under Settings → Attendance Configuration.
 *
 * Both methods default to enabled, so a company that has never opened the new
 * settings section keeps the behaviour it had before they existed.
 *
 * This governs the navigation only. The underlying routes stay reachable and
 * keep their own permission middleware — turning a method off removes it from
 * the menu, it does not revoke access to the page.
 */
class AttendanceMethods
{
    public const SYSTEM = 'system';
    public const QR = 'qr';

    public const SETTING_SYSTEM = 'attendance_method_system';
    public const SETTING_QR = 'attendance_method_qr';

    public static function systemEnabled(): bool
    {
        return self::isOn(self::SETTING_SYSTEM);
    }

    public static function qrEnabled(): bool
    {
        return self::isOn(self::SETTING_QR);
    }

    /**
     * The submenu items this user should actually see: enabled by the
     * administrator *and* permitted for them.
     *
     * @return array<int, array{key: string, label: string, icon: string, url: string, active: bool}>
     */
    public static function navItemsFor(?User $user): array
    {
        $items = [];

        if (self::systemEnabled() && ($user?->hasAnyPermission(['attendance.view', 'attendance.clock', 'attendance.manage']) ?? false)) {
            $items[] = [
                'key' => self::SYSTEM,
                'label' => __('System-Based Attendance'),
                'icon' => 'icon-screen-desktop',
                'url' => route('attendance.index'),
                'active' => request()->routeIs('attendance.index'),
            ];
        }

        if (self::qrEnabled() && ($user?->hasAnyPermission(['attendance.scan', 'attendance.manage']) ?? false)) {
            $items[] = [
                'key' => self::QR,
                'label' => __('QR Code Attendance'),
                'icon' => 'icon-screen-smartphone',
                'url' => route('attendance.scan.index'),
                'active' => request()->routeIs('attendance.scan.*'),
            ];
        }

        return $items;
    }

    /**
     * Settings are stored as strings; anything other than an explicit "0" counts
     * as on, so a missing row (never saved) means enabled.
     */
    private static function isOn(string $key): bool
    {
        return (string) SystemSetting::getValue($key, '1') !== '0';
    }
}
