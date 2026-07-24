<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Where an employee is allowed to mark system-based attendance from: an IP
 * allowlist, a geofence around a fixed point, or both.
 *
 * These apply to System-Based Attendance only. QR Code Attendance is recorded by
 * a scanner the administrator controls and operates, so the employee's own
 * network and position are not what is being vouched for there.
 *
 * Both restrictions are off unless switched on, so an existing company is
 * unaffected until an administrator configures them.
 */
class AttendanceRestrictions
{
    public const SETTING_IP_ENABLED = 'attendance_ip_restriction_enabled';
    public const SETTING_ALLOWED_IPS = 'attendance_allowed_ips';
    public const SETTING_GEO_ENABLED = 'attendance_geofence_enabled';
    public const SETTING_LATITUDE = 'attendance_geofence_latitude';
    public const SETTING_LONGITUDE = 'attendance_geofence_longitude';
    public const SETTING_RADIUS = 'attendance_geofence_radius';

    public const DEFAULT_RADIUS_METRES = 200;

    public static function ipRestrictionEnabled(): bool
    {
        return (string) SystemSetting::getValue(self::SETTING_IP_ENABLED, '0') === '1';
    }

    public static function geofenceEnabled(): bool
    {
        return (string) SystemSetting::getValue(self::SETTING_GEO_ENABLED, '0') === '1'
            && self::latitude() !== null
            && self::longitude() !== null;
    }

    public static function latitude(): ?float
    {
        $value = SystemSetting::getValue(self::SETTING_LATITUDE);

        return is_numeric($value) ? (float) $value : null;
    }

    public static function longitude(): ?float
    {
        $value = SystemSetting::getValue(self::SETTING_LONGITUDE);

        return is_numeric($value) ? (float) $value : null;
    }

    public static function radiusMetres(): int
    {
        $value = SystemSetting::getValue(self::SETTING_RADIUS);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : self::DEFAULT_RADIUS_METRES;
    }

    /**
     * The allowlist, one entry per line or comma separated. Entries are exact
     * addresses (v4 or v6) or IPv4 CIDR blocks such as 203.0.113.0/24.
     *
     * @return array<int, string>
     */
    public static function allowedIps(): array
    {
        $raw = (string) SystemSetting::getValue(self::SETTING_ALLOWED_IPS, '');

        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[\s,]+/', $raw) ?: []
        ), fn (string $entry): bool => $entry !== ''));
    }

    /**
     * An empty allowlist never blocks. Configuring the restriction but listing
     * nothing is far more likely to be a half-finished setup than an intent to
     * lock every employee out of attendance.
     */
    public static function ipAllowed(?string $ip): bool
    {
        if (! self::ipRestrictionEnabled()) {
            return true;
        }

        $allowed = self::allowedIps();
        if ($allowed === []) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        foreach ($allowed as $entry) {
            if (self::ipMatches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Distance in metres between the configured point and the given position,
     * or null when the geofence is off or the position is unknown.
     */
    public static function distanceFromSite(?float $latitude, ?float $longitude): ?float
    {
        if (! self::geofenceEnabled() || $latitude === null || $longitude === null) {
            return null;
        }

        return self::haversineMetres(
            (float) self::latitude(),
            (float) self::longitude(),
            $latitude,
            $longitude
        );
    }

    public static function withinGeofence(?float $latitude, ?float $longitude): bool
    {
        if (! self::geofenceEnabled()) {
            return true;
        }

        $distance = self::distanceFromSite($latitude, $longitude);

        // No position supplied while the geofence is on: the browser refused or
        // could not resolve it, and an unverified location cannot pass.
        if ($distance === null) {
            return false;
        }

        return $distance <= self::radiusMetres();
    }

    private static function ipMatches(string $ip, string $entry): bool
    {
        if (! str_contains($entry, '/')) {
            return strcasecmp($ip, $entry) === 0;
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        // CIDR is handled for IPv4 only; an IPv6 range would need 128-bit maths
        // that PHP's integer type cannot do portably.
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || ! is_numeric($bits)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private static function haversineMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
