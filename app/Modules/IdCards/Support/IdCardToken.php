<?php

namespace App\Modules\IdCards\Support;

/**
 * Builds and verifies the signed token embedded in an employee ID card QR.
 *
 * Format: SHRID1.{employeeId}.{serial}.{signature}
 * The signature is an HMAC-SHA256 of "{employeeId}|{serial}" keyed by the app
 * secret, so a card cannot be forged or guessed, and revoking a card row
 * invalidates it regardless of the signature.
 */
class IdCardToken
{
    private const PREFIX = 'SHRID1';

    public static function make(int $employeeId, string $serial): string
    {
        return implode('.', [self::PREFIX, $employeeId, $serial, self::signature($employeeId, $serial)]);
    }

    public static function signature(int $employeeId, string $serial): string
    {
        return substr(hash_hmac('sha256', $employeeId . '|' . $serial, self::secret()), 0, 32);
    }

    /**
     * Parse and verify a scanned payload.
     *
     * @return array{employee_id: int, serial: string}|null  null when malformed or signature invalid
     */
    public static function parse(string $payload): ?array
    {
        $parts = explode('.', trim($payload));
        if (count($parts) !== 4) {
            return null;
        }

        [$prefix, $employeeId, $serial, $signature] = $parts;

        if ($prefix !== self::PREFIX || ! ctype_digit($employeeId) || $serial === '') {
            return null;
        }

        $expected = self::signature((int) $employeeId, $serial);
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return ['employee_id' => (int) $employeeId, 'serial' => $serial];
    }

    private static function secret(): string
    {
        return (string) config('app.key');
    }
}
