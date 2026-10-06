<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RupiahHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../app/Core/View.php';
    }

    public function testFormatsWholeRupiahWithDotThousandsSeparator(): void
    {
        $this->assertSame('Rp 636.128.000', rupiah(636128000.0));
        $this->assertSame('Rp 15.000', rupiah('15000.00'));
        $this->assertSame('Rp 0', rupiah(null));
    }

    public function testRoundsToWholeRupiahAndKeepsTheSignOfNegativeAmounts(): void
    {
        $this->assertSame('Rp 1.001', rupiah(1000.6));
        $this->assertSame('-Rp 2.500', rupiah(-2500));
        $this->assertSame('Rp 0', rupiah(-0.2));
    }
}
