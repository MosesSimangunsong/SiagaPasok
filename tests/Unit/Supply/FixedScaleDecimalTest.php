<?php

namespace Tests\Unit\Supply;

use App\Support\FixedScaleDecimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FixedScaleDecimalTest extends TestCase
{
    public function test_it_normalizes_quantity_to_six_decimals(): void
    {
        $this->assertSame(
            '12.300000',
            FixedScaleDecimal::from('12.3')
                ->toString()
        );

        $this->assertSame(
            '0.000000',
            FixedScaleDecimal::zero()
                ->toString()
        );
    }

    public function test_addition_is_exact_without_binary_float(): void
    {
        $result =
            FixedScaleDecimal::from('0.100000')
                ->add(
                    FixedScaleDecimal::from(
                        '0.200000'
                    )
                );

        $this->assertSame(
            '0.300000',
            $result->toString()
        );
    }

    public function test_subtraction_is_floored_at_zero(): void
    {
        $this->assertSame(
            '25.250000',
            FixedScaleDecimal::from(
                '100.500000'
            )
                ->subtractToZero(
                    FixedScaleDecimal::from(
                        '75.250000'
                    )
                )
                ->toString()
        );

        $this->assertSame(
            '0.000000',
            FixedScaleDecimal::from(
                '10.000000'
            )
                ->subtractToZero(
                    FixedScaleDecimal::from(
                        '20.000000'
                    )
                )
                ->toString()
        );
    }

    public function test_exact_comparison_is_available_for_business_gate(): void
    {
        $safe =
            FixedScaleDecimal::from(
                '100.000000'
            );

        $demand =
            FixedScaleDecimal::from(
                '100.000000'
            );

        $this->assertTrue(
            $safe->greaterThanOrEqual(
                $demand
            )
        );

        $this->assertSame(
            0,
            $safe->compare($demand)
        );
    }

    public function test_percentage_is_capped_at_one_hundred(): void
    {
        $this->assertSame(
            '100.00',
            FixedScaleDecimal::from(
                '125.000000'
            )->percentageOf(
                FixedScaleDecimal::from(
                    '100.000000'
                )
            )
        );
    }

    public function test_percentage_uses_half_up_rounding(): void
    {
        $this->assertSame(
            '68.25',
            FixedScaleDecimal::from(
                '273.000000'
            )->percentageOf(
                FixedScaleDecimal::from(
                    '400.000000'
                )
            )
        );

        $this->assertSame(
            '66.67',
            FixedScaleDecimal::from(
                '2.000000'
            )->percentageOf(
                FixedScaleDecimal::from(
                    '3.000000'
                )
            )
        );
    }

    public function test_zero_denominator_returns_null_percentage(): void
    {
        $this->assertNull(
            FixedScaleDecimal::from(
                '10.000000'
            )->percentageOf(
                FixedScaleDecimal::zero()
            )
        );
    }

    public function test_more_than_six_fractional_digits_is_rejected(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        FixedScaleDecimal::from(
            '1.1234567'
        );
    }
}