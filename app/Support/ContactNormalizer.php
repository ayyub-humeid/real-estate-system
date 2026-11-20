<?php

namespace App\Support;

/**
 * One definition of "the same phone / the same email", shared by leads and
 * parties so a lead can be matched to an existing client reliably.
 */
class ContactNormalizer
{
    /** "0591234567" and "+970 59 123 4567" become the same "+970591234567". */
    public static function phone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+970'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    public static function email(?string $email): ?string
    {
        return filled($email) ? strtolower(trim($email)) : null;
    }
}
