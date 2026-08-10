<?php

namespace App\Support;

use InvalidArgumentException;
use LogicException;

final class FixedScaleDecimal
{
    public const SCALE = 6;

    private function __construct(
        private readonly string $scaledDigits,
    ) {
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public static function from(
        string|int $value,
    ): self {
        $raw = trim((string) $value);

        if ($raw === '') {
            throw new InvalidArgumentException(
                'Decimal value tidak boleh kosong.'
            );
        }

        if (
            ! preg_match(
                '/^\+?(\d+)(?:\.(\d+))?$/',
                $raw,
                $matches
            )
        ) {
            throw new InvalidArgumentException(
                "Decimal value tidak valid: {$raw}"
            );
        }

        $integerPart = $matches[1];

        $fractionPart =
            $matches[2] ?? '';

        if (
            strlen($fractionPart)
            > self::SCALE
        ) {
            throw new InvalidArgumentException(
                'Decimal quantity melebihi '
                .self::SCALE
                .' digit fractional.'
            );
        }

        $fractionPart = str_pad(
            $fractionPart,
            self::SCALE,
            '0'
        );

        return new self(
            self::normalizeUnsignedInteger(
                $integerPart.$fractionPart
            )
        );
    }

    public function add(
        self $other,
    ): self {
        return new self(
            self::addUnsignedIntegers(
                $this->scaledDigits,
                $other->scaledDigits
            )
        );
    }

    public function subtractToZero(
        self $other,
    ): self {
        if (
            $this->compare($other)
            <= 0
        ) {
            return self::zero();
        }

        return new self(
            self::subtractUnsignedIntegers(
                $this->scaledDigits,
                $other->scaledDigits
            )
        );
    }

    public function compare(
        self $other,
    ): int {
        return self::compareUnsignedIntegers(
            $this->scaledDigits,
            $other->scaledDigits
        );
    }

    public function isZero(): bool
    {
        return $this->scaledDigits === '0';
    }

    public function greaterThanOrEqual(
        self $other,
    ): bool {
        return $this->compare($other) >= 0;
    }

    public function toString(): string
    {
        $digits = str_pad(
            $this->scaledDigits,
            self::SCALE + 1,
            '0',
            STR_PAD_LEFT
        );

        $integerPart = substr(
            $digits,
            0,
            -self::SCALE
        );

        $fractionPart = substr(
            $digits,
            -self::SCALE
        );

        return $integerPart.'.'.$fractionPart;
    }

    /**
     * Menghasilkan percentage dengan ROUND HALF UP.
     *
     * Nilai >= denominator dikunci pada 100%.
     * Denominator 0 menghasilkan null.
     *
     * Method ini tidak dipakai sebagai business gate.
     */
    public function percentageOf(
        self $denominator,
        int $decimalPlaces = 2,
    ): ?string {
        if ($decimalPlaces < 0) {
            throw new InvalidArgumentException(
                'Decimal places tidak boleh negatif.'
            );
        }

        if ($denominator->isZero()) {
            return null;
        }

        if (
            $this->greaterThanOrEqual(
                $denominator
            )
        ) {
            return self::formatPercentageInteger(
                '1'.str_repeat(
                    '0',
                    $decimalPlaces + 2
                ),
                $decimalPlaces
            );
        }

        /*
         * Untuk percentage:
         *
         * value / denominator × 100
         *
         * Untuk output 2 decimal:
         *
         * scaled percentage integer
         * = value × 10^(2 + decimalPlaces)
         *   / denominator.
         *
         * Dilakukan dengan string integer arithmetic
         * agar tidak memakai binary floating point.
         */
        $numerator =
            $this->scaledDigits === '0'
                ? '0'
                : $this->scaledDigits
                    .str_repeat(
                        '0',
                        $decimalPlaces + 2
                    );

        [
            $quotient,
            $remainder,
        ] = self::divideUnsignedIntegers(
            $numerator,
            $denominator->scaledDigits
        );

        /*
         * ROUND HALF UP:
         * remainder / denominator >= 0.5
         */
        $twiceRemainder =
            self::multiplyUnsignedByDigit(
                $remainder,
                2
            );

        if (
            self::compareUnsignedIntegers(
                $twiceRemainder,
                $denominator->scaledDigits
            ) >= 0
        ) {
            $quotient =
                self::addUnsignedIntegers(
                    $quotient,
                    '1'
                );
        }

        return self::formatPercentageInteger(
            $quotient,
            $decimalPlaces
        );
    }

    private static function formatPercentageInteger(
        string $digits,
        int $decimalPlaces,
    ): string {
        $digits =
            self::normalizeUnsignedInteger(
                $digits
            );

        if ($decimalPlaces === 0) {
            return $digits;
        }

        $digits = str_pad(
            $digits,
            $decimalPlaces + 1,
            '0',
            STR_PAD_LEFT
        );

        return substr(
            $digits,
            0,
            -$decimalPlaces
        )
            .'.'
            .substr(
                $digits,
                -$decimalPlaces
            );
    }

    private static function normalizeUnsignedInteger(
        string $digits,
    ): string {
        if (
            $digits === ''
            || ! ctype_digit($digits)
        ) {
            throw new InvalidArgumentException(
                'Unsigned integer digits tidak valid.'
            );
        }

        $normalized = ltrim(
            $digits,
            '0'
        );

        return $normalized === ''
            ? '0'
            : $normalized;
    }

    private static function compareUnsignedIntegers(
        string $left,
        string $right,
    ): int {
        $left =
            self::normalizeUnsignedInteger(
                $left
            );

        $right =
            self::normalizeUnsignedInteger(
                $right
            );

        $lengthComparison =
            strlen($left)
            <=> strlen($right);

        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return $left <=> $right;
    }

    private static function addUnsignedIntegers(
        string $left,
        string $right,
    ): string {
        $left =
            self::normalizeUnsignedInteger(
                $left
            );

        $right =
            self::normalizeUnsignedInteger(
                $right
            );

        $leftIndex =
            strlen($left) - 1;

        $rightIndex =
            strlen($right) - 1;

        $carry = 0;
        $result = '';

        while (
            $leftIndex >= 0
            || $rightIndex >= 0
            || $carry > 0
        ) {
            $leftDigit =
                $leftIndex >= 0
                    ? (int) $left[$leftIndex]
                    : 0;

            $rightDigit =
                $rightIndex >= 0
                    ? (int) $right[$rightIndex]
                    : 0;

            $sum =
                $leftDigit
                + $rightDigit
                + $carry;

            $result =
                (string) ($sum % 10)
                .$result;

            $carry =
                intdiv(
                    $sum,
                    10
                );

            $leftIndex--;
            $rightIndex--;
        }

        return self::normalizeUnsignedInteger(
            $result
        );
    }

    private static function subtractUnsignedIntegers(
        string $left,
        string $right,
    ): string {
        $left =
            self::normalizeUnsignedInteger(
                $left
            );

        $right =
            self::normalizeUnsignedInteger(
                $right
            );

        if (
            self::compareUnsignedIntegers(
                $left,
                $right
            ) < 0
        ) {
            throw new LogicException(
                'Unsigned subtraction menghasilkan nilai negatif.'
            );
        }

        $leftIndex =
            strlen($left) - 1;

        $rightIndex =
            strlen($right) - 1;

        $borrow = 0;
        $result = '';

        while ($leftIndex >= 0) {
            $leftDigit =
                (int) $left[$leftIndex]
                - $borrow;

            $rightDigit =
                $rightIndex >= 0
                    ? (int) $right[$rightIndex]
                    : 0;

            if ($leftDigit < $rightDigit) {
                $leftDigit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result =
                (string) (
                    $leftDigit
                    - $rightDigit
                )
                .$result;

            $leftIndex--;
            $rightIndex--;
        }

        return self::normalizeUnsignedInteger(
            $result
        );
    }

    private static function multiplyUnsignedByDigit(
        string $value,
        int $digit,
    ): string {
        if (
            $digit < 0
            || $digit > 9
        ) {
            throw new InvalidArgumentException(
                'Multiplier harus satu digit 0-9.'
            );
        }

        $value =
            self::normalizeUnsignedInteger(
                $value
            );

        if (
            $digit === 0
            || $value === '0'
        ) {
            return '0';
        }

        $carry = 0;
        $result = '';

        for (
            $index = strlen($value) - 1;
            $index >= 0;
            $index--
        ) {
            $product =
                ((int) $value[$index])
                * $digit
                + $carry;

            $result =
                (string) ($product % 10)
                .$result;

            $carry =
                intdiv(
                    $product,
                    10
                );
        }

        if ($carry > 0) {
            $result =
                (string) $carry
                .$result;
        }

        return self::normalizeUnsignedInteger(
            $result
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function divideUnsignedIntegers(
        string $dividend,
        string $divisor,
    ): array {
        $dividend =
            self::normalizeUnsignedInteger(
                $dividend
            );

        $divisor =
            self::normalizeUnsignedInteger(
                $divisor
            );

        if ($divisor === '0') {
            throw new LogicException(
                'Division by zero.'
            );
        }

        if (
            self::compareUnsignedIntegers(
                $dividend,
                $divisor
            ) < 0
        ) {
            return [
                '0',
                $dividend,
            ];
        }

        $quotient = '';
        $remainder = '0';

        foreach (
            str_split($dividend)
            as $digit
        ) {
            $remainder =
                self::normalizeUnsignedInteger(
                    (
                        $remainder === '0'
                            ? ''
                            : $remainder
                    )
                    .$digit
                );

            $quotientDigit = 0;

            for (
                $candidate = 9;
                $candidate >= 1;
                $candidate--
            ) {
                $product =
                    self::multiplyUnsignedByDigit(
                        $divisor,
                        $candidate
                    );

                if (
                    self::compareUnsignedIntegers(
                        $product,
                        $remainder
                    ) <= 0
                ) {
                    $quotientDigit =
                        $candidate;

                    $remainder =
                        self::subtractUnsignedIntegers(
                            $remainder,
                            $product
                        );

                    break;
                }
            }

            $quotient .=
                (string) $quotientDigit;
        }

        return [
            self::normalizeUnsignedInteger(
                $quotient
            ),
            self::normalizeUnsignedInteger(
                $remainder
            ),
        ];
    }
}