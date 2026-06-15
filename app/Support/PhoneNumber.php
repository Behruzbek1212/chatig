<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize an Uzbek phone number to +998XXXXXXXXX.
     * Returns null if it cannot be normalized to a 9-digit national number.
     */
    public static function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // Strip leading country code variants.
        if (str_starts_with($digits, '998')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '8') && strlen($digits) === 10) {
            // e.g. 8 90 123 45 67 -> drop leading 8
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 9) {
            return null;
        }

        return '+998'.$digits;
    }

    public static function isValid(string $raw): bool
    {
        return self::normalize($raw) !== null;
    }
}
