<?php

namespace App\Support;

use App\Enums\RentFrequency;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Carbon;

/**
 * Turns rent terms (rent per payment + frequency + term) into the figures a
 * contract needs: how many payments there are and what the whole term is worth.
 *
 * A rental is agreed as "500 a month for a year", while a contract is signed for
 * a total. Doing this in one place keeps the contract, its payment schedule and
 * the rent roll from disagreeing.
 */
final class RentTerms
{
    public static function monthsPerPeriod(RentFrequency $frequency): int
    {
        return match ($frequency) {
            RentFrequency::Monthly => 1,
            RentFrequency::Quarterly => 3,
            RentFrequency::SemiAnnually => 6,
            RentFrequency::Yearly => 12,
        };
    }

    /** The number of payments in the term, or null when the term is not a whole number of periods. */
    public static function periods(RentFrequency $frequency, ?int $termMonths): ?int
    {
        $perPeriod = self::monthsPerPeriod($frequency);

        if (! $termMonths || $termMonths < $perPeriod || $termMonths % $perPeriod !== 0) {
            return null;
        }

        return intdiv($termMonths, $perPeriod);
    }

    /** Whole months covered by a term that runs to the end of its last day. */
    public static function monthsBetween(mixed $start, mixed $end): ?int
    {
        $from = self::date($start);
        $to = self::date($end);

        if (! $from || ! $to || $to->lessThanOrEqualTo($from)) {
            return null;
        }

        // A lease dated 01/02 to 31/01 covers 12 months, not 11.
        $months = (int) $from->diffInMonths($to->copy()->addDay());

        return $months > 0 ? $months : null;
    }

    /** The term's value as a money string, or null when the inputs are unusable. */
    public static function total(mixed $rentPerPeriod, ?int $periods): ?string
    {
        if (! $periods || $periods < 1 || ! is_numeric($rentPerPeriod)) {
            return null;
        }

        try {
            $rent = BigDecimal::of((string) $rentPerPeriod);
        } catch (MathException) {
            return null;
        }

        return $rent->isPositive() ? (string) $rent->multipliedBy($periods)->toScale(2) : null;
    }

    /**
     * The total for a set of rent terms, over the term the contract's own start
     * and end dates describe.
     *
     * @return array{frequency: ?RentFrequency, periods: ?int, total: ?string}
     */
    public static function summarise(mixed $rentPerPeriod, mixed $frequency, mixed $start = null, mixed $end = null): array
    {
        $rentFrequency = $frequency instanceof RentFrequency
            ? $frequency
            : RentFrequency::tryFrom((string) $frequency);

        if (! $rentFrequency) {
            return ['frequency' => null, 'periods' => null, 'total' => null];
        }

        $periods = self::periods($rentFrequency, self::monthsBetween($start, $end));

        return [
            'frequency' => $rentFrequency,
            'periods' => $periods,
            'total' => self::total($rentPerPeriod, $periods),
        ];
    }

    /** The last day covered by a term that starts on $start. */
    public static function endDate(mixed $start, ?int $termMonths): ?string
    {
        $from = self::date($start);

        return $from && $termMonths && $termMonths > 0
            ? $from->copy()->addMonths($termMonths)->subDay()->toDateString()
            : null;
    }

    private static function date(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
