<?php

namespace App\Support;

class Format
{
    /**
     * Format a numeric amount in Indonesian Rupiah.
     */
    public static function rupiah(float|int|string|null $amount): string
    {
        $numeric = (float) ($amount ?? 0);
        $prefix = $numeric < 0 ? '-Rp ' : 'Rp ';

        return $prefix . number_format(abs($numeric), 0, ',', '.');
    }

    /**
     * Format a product quantity cleanly (strip trailing zeros).
     */
    public static function quantity(float|int|string|null $qty): string
    {
        $numeric = (float) ($qty ?? 0);
        if (fmod($numeric, 1.0) === 0.0) {
            return number_format($numeric, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($numeric, 3, ',', '.'), '0'), ',');
    }
}
