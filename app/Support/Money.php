<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Money value object. Amounts are integer minor units with an ISO 4217
 * currency code. Formatting is currency-aware (JPY has 0 decimals, BHD/KWD
 * have 3) and integer-safe — no float arithmetic anywhere in the domain.
 */
final class Money
{
    /** Currencies whose minor-unit exponent differs from the default of 2. */
    private const EXPONENTS = [
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'CLP' => 0, 'ISK' => 0,
        'BHD' => 3, 'KWD' => 3, 'OMR' => 3, 'TND' => 3, 'IQD' => 3,
    ];

    public function __construct(
        public readonly int $minor,
        public readonly string $currency = 'USD',
    ) {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Invalid currency code: {$currency}");
        }
    }

    public static function of(int $minor, string $currency = 'USD'): self
    {
        return new self($minor, strtoupper($currency));
    }

    public function exponent(): int
    {
        return self::EXPONENTS[strtoupper($this->currency)] ?? 2;
    }

    /**
     * Format without floats: split into whole/fraction using integer math so
     * currencies with 0 or 3 decimals render correctly.
     */
    public function format(): string
    {
        $exp = $this->exponent();
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);

        if ($exp === 0) {
            return $sign.number_format($abs).' '.$this->currency;
        }

        $divisor = 10 ** $exp;
        $whole = intdiv($abs, $divisor);
        $fraction = str_pad((string) ($abs % $divisor), $exp, '0', STR_PAD_LEFT);

        return $sign.number_format($whole).'.'.$fraction.' '.$this->currency;
    }
}
