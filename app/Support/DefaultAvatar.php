<?php

namespace App\Support;

/**
 * Resolves the fallback avatar shown when a user/employee has not uploaded a
 * profile photo. The image is picked by gender; when the gender is unknown
 * (null, empty, "other", or anything unrecognised) the male image is used.
 *
 * Returns a path relative to public/, so callers wrap it in asset().
 */
class DefaultAvatar
{
    public const MALE = 'assets/img/user/default-male.svg';
    public const FEMALE = 'assets/img/user/default-female.svg';

    public static function forGender(?string $gender): string
    {
        return strtolower(trim((string) $gender)) === 'female'
            ? self::FEMALE
            : self::MALE;
    }
}
