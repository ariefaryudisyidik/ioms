<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IconHelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../views/partials/partial.php';
    }

    public function testRendersInlineSvgWithoutFixedSizeOnTheRootTag(): void
    {
        $svg = icon('package');

        $this->assertStringStartsWith('<svg class="icon"', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertStringContainsString('stroke="currentColor"', $svg);
        $this->assertDoesNotMatchRegularExpression('/<svg[^>]*\swidth=/', $svg);
    }

    public function testKeepsSizeAttributesOfInnerShapes(): void
    {
        $this->assertMatchesRegularExpression('/<rect width="/', icon('layout-dashboard'));
    }

    public function testRejectsUnsafeOrUnknownNames(): void
    {
        $this->assertSame('', icon('../../etc/passwd'));
        $this->assertSame('', icon('does-not-exist'));
    }

    public function testRoleLabelSplitsCamelCaseForDisplay(): void
    {
        $this->assertSame('Warehouse Staff', roleLabel('WarehouseStaff'));
        $this->assertSame('Partially Received', statusLabel('PartiallyReceived'));
        $this->assertSame('Pending Approval', statusLabel('PendingApproval'));
        $this->assertSame('Draft', statusLabel('Draft'));
        $this->assertSame('Admin', roleLabel('Admin'));
        $this->assertSame('Sales', roleLabel('Sales'));
    }
}
