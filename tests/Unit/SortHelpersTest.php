<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SortHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../views/partials/partial.php';
    }

    public function testDirectionIsOnlyReportedForTheSortedColumn(): void
    {
        $this->assertSame('asc', sortDirection('name_asc', 'name'));
        $this->assertSame('desc', sortDirection('purchase_price_desc', 'purchase_price'));
        $this->assertSame('', sortDirection('name_asc', 'price'));
        $this->assertSame('', sortDirection('', 'name'));
    }

    public function testFirstClickSortsAscendingAndEachClickFlipsTheDirection(): void
    {
        $this->assertSame('/suppliers?sort=name_asc', sortUrl('/suppliers', [], 'name', ''));
        $this->assertSame('/suppliers?sort=name_desc', sortUrl('/suppliers', [], 'name', 'name_asc'));
        $this->assertSame('/suppliers?sort=name_asc', sortUrl('/suppliers', [], 'name', 'name_desc'));
        $this->assertSame('/suppliers?sort=contact_asc', sortUrl('/suppliers', [], 'contact', 'name_desc'));
    }

    public function testFiltersStayInTheLinkAndEmptyOnesAreDropped(): void
    {
        $url = sortUrl('/products', ['search' => 'kabel', 'status' => '', 'category_id' => null, 'page' => ''], 'sku', 'name_asc');

        $this->assertSame('/products?search=kabel&sort=sku_asc', $url);
    }
}
