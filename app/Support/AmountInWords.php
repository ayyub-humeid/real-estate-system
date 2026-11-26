<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use NumberFormatter;

/**
 * Writes a contract amount in words. Arabic is built here because the ICU
 * spell-out rules produce ungrammatical Arabic, which a legal document cannot
 * carry. Fractions are written as a ratio so they stay unambiguous.
 */
final class AmountInWords
{
    private const AR_ONES = [
        'صفر', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
        'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر',
    ];

    private const AR_TENS = [2 => 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const AR_HUNDREDS = [1 => 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** Largest first: [singular, dual, dual before a noun, plural, accusative]. */
    private const AR_SCALES = [
        1_000_000_000_000 => ['ترليون', 'ترليونان', 'ترليونا', 'ترليونات', 'ترليوناً'],
        1_000_000_000 => ['مليار', 'ملياران', 'مليارا', 'مليارات', 'ملياراً'],
        1_000_000 => ['مليون', 'مليونان', 'مليونا', 'ملايين', 'مليوناً'],
        1_000 => ['ألف', 'ألفان', 'ألفا', 'آلاف', 'ألفاً'],
    ];

    /** [singular, dual, plural, accusative]. Every listed noun is masculine. */
    private const AR_CURRENCIES = [
        'USD' => ['دولار أمريكي', 'دولاران أمريكيان', 'دولارات أمريكية', 'دولاراً أمريكياً'],
        'ILS' => ['شيكل إسرائيلي', 'شيكلان إسرائيليان', 'شواكل إسرائيلية', 'شيكلاً إسرائيلياً'],
    ];

    private const EN_CURRENCIES = [
        'USD' => ['US dollar', 'US dollars'],
        'ILS' => ['Israeli shekel', 'Israeli shekels'],
    ];

    private const LIMIT = 1_000_000_000_000_000;

    public static function format(mixed $amount, ?string $currency, string $locale): string
    {
        $decimal = BigDecimal::of((string) $amount)->toScale(2, RoundingMode::HALF_UP);
        [$whole, $fraction] = explode('.', (string) $decimal->abs());
        $currency = (string) $currency;

        if (strlen($whole) >= strlen((string) self::LIMIT)) {
            return trim($decimal.' '.$currency);
        }

        $number = (int) $whole;
        $arabic = $locale === 'ar';
        $words = $arabic ? self::arabic($number, $currency) : self::english($number, $currency);

        if ($fraction !== '00') {
            $words .= ($arabic ? ' و' : ' and ').$fraction.'/100';
        }

        return $arabic ? "فقط {$words} لا غير" : ucfirst($words).' only';
    }

    private static function english(int $number, string $currency): string
    {
        [$singular, $plural] = self::EN_CURRENCIES[$currency] ?? [$currency, $currency];
        $words = (new NumberFormatter('en', NumberFormatter::SPELLOUT))->format($number);

        return trim($words.' '.($number === 1 ? $singular : $plural));
    }

    private static function arabic(int $number, string $currency): string
    {
        [$singular, $dual, $plural, $accusative] = self::AR_CURRENCIES[$currency]
            ?? [$currency, $currency, $currency, $currency];

        if ($number === 1) {
            return $singular.' واحد';
        }
        if ($number === 2) {
            return $dual;
        }

        $lastTwo = $number % 100;
        $noun = match (true) {
            $lastTwo >= 3 && $lastTwo <= 10 => $plural,
            $lastTwo >= 11 => $accusative,
            default => $singular,
        };

        return trim(self::arabicNumber($number).' '.$noun);
    }

    /** A number that is immediately followed by the noun it counts. */
    private static function arabicNumber(int $number): string
    {
        if ($number === 0) {
            return self::AR_ONES[0];
        }

        $parts = [];
        foreach (self::AR_SCALES as $size => [$singular, $dual, $dualBeforeNoun, $plural, $accusative]) {
            $count = intdiv($number, $size);
            $number %= $size;
            if ($count === 0) {
                continue;
            }

            // The last part leans on the currency noun, which drops its ending.
            $isLast = $number === 0;
            $lastTwo = $count % 100;
            $parts[] = match (true) {
                $count === 1 => $singular,
                $count === 2 => $isLast ? $dualBeforeNoun : $dual,
                $lastTwo >= 3 && $lastTwo <= 10 => self::arabicBelowThousand($count).' '.$plural,
                $lastTwo >= 11 => self::arabicBelowThousand($count).' '.($isLast ? $singular : $accusative),
                default => self::arabicBelowThousand($count).' '.$singular,
            };
        }

        if ($number > 0) {
            $parts[] = self::arabicBelowThousand($number);
        }

        return implode(' و', $parts);
    }

    private static function arabicBelowThousand(int $number): string
    {
        $parts = [];
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        if ($hundreds > 0) {
            $parts[] = $hundreds === 2 && $rest === 0 ? 'مائتا' : self::AR_HUNDREDS[$hundreds];
        }

        if ($rest >= 20) {
            if ($rest % 10 > 0) {
                $parts[] = self::AR_ONES[$rest % 10];
            }
            $parts[] = self::AR_TENS[intdiv($rest, 10)];
        } elseif ($rest > 0) {
            $parts[] = self::AR_ONES[$rest];
        }

        return implode(' و', $parts);
    }
}
