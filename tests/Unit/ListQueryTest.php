<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\ListQuery;
use PHPUnit\Framework\TestCase;

final class ListQueryTest extends TestCase
{
    /** @return list<object> */
    private function items(): array
    {
        return array_map(
            static fn (array $row): object => (object) $row,
            [
                ['name' => 'Beta Store', 'contact' => '0812', 'active' => true, 'qty' => 10],
                ['name' => 'alpha Depot', 'contact' => null, 'active' => false, 'qty' => 9],
                ['name' => 'Item 10', 'contact' => 'budi@x.test', 'active' => true, 'qty' => 100],
                ['name' => 'Item 9', 'contact' => '', 'active' => false, 'qty' => 2],
            ]
        );
    }

    /** @return array<string, callable(object):mixed> */
    private function columns(): array
    {
        return [
            'name' => static fn (object $o): string => $o->name,
            'contact' => static fn (object $o): ?string => $o->contact,
            'status' => static fn (object $o): bool => $o->active,
            'qty' => static fn (object $o): int => $o->qty,
        ];
    }

    /** @param array<string,string> $params */
    private function names(array $params, ?string $statusColumn = 'status'): array
    {
        $result = ListQuery::apply($this->items(), $params, $this->columns(), ['name', 'contact'], $statusColumn);

        return array_map(static fn (object $o): string => $o->name, $result);
    }

    public function testDefaultSortIsNameAscendingIgnoringCase(): void
    {
        $this->assertSame(['alpha Depot', 'Beta Store', 'Item 9', 'Item 10'], $this->names([]));
    }

    public function testSearchMatchesAnyConfiguredColumnCaseInsensitively(): void
    {
        $this->assertSame(['Beta Store'], $this->names(['search' => 'BETA']));
        $this->assertSame(['Item 10'], $this->names(['search' => 'BUDI@']));
        $this->assertSame(['Beta Store'], $this->names(['search' => '  0812  ']));
    }

    public function testNullColumnsAreSearchedAsEmptyText(): void
    {
        $this->assertSame([], $this->names(['search' => 'null']));
    }

    public function testStatusFilterKeepsActiveOrInactiveAndIgnoresUnknownValues(): void
    {
        $this->assertSame(['Beta Store', 'Item 10'], $this->names(['status' => 'active']));
        $this->assertSame(['alpha Depot', 'Item 9'], $this->names(['status' => 'inactive']));
        $this->assertCount(4, $this->names(['status' => 'bogus']));
    }

    public function testStatusIsIgnoredWhenTheListHasNoStatusColumn(): void
    {
        $this->assertCount(4, $this->names(['status' => 'inactive'], null));
    }

    public function testSortsAscendingAndDescendingByColumn(): void
    {
        $this->assertSame(['Item 9', 'alpha Depot', 'Beta Store', 'Item 10'], $this->names(['sort' => 'qty_asc']));
        $this->assertSame(['Item 10', 'Beta Store', 'alpha Depot', 'Item 9'], $this->names(['sort' => 'qty_desc']));
        $this->assertSame(['Item 10', 'Item 9', 'Beta Store', 'alpha Depot'], $this->names(['sort' => 'name_desc']));
    }

    public function testNumbersSortNumericallyAndTextNaturally(): void
    {
        $this->assertSame(['Item 9', 'Item 10'], $this->names(['search' => 'item', 'sort' => 'name_asc']));
        $this->assertSame(['Item 9', 'alpha Depot', 'Beta Store', 'Item 10'], $this->names(['sort' => 'qty_asc']));
    }

    public function testUnknownColumnOrDirectionFallsBackToTheDefaultSort(): void
    {
        $default = ['alpha Depot', 'Beta Store', 'Item 9', 'Item 10'];
        $this->assertSame($default, $this->names(['sort' => 'bogus_asc']));
        $this->assertSame($default, $this->names(['sort' => 'qty_sideways']));
        $this->assertSame($default, $this->names(['sort' => 'nounderscore']));
    }

    public function testOrderSortAcceptsEveryOrderColumnAndTheLegacyDateValues(): void
    {
        foreach (['number', 'party', 'date', 'status'] as $column) {
            $this->assertSame($column . '_asc', ListQuery::normalizeOrderSort($column . '_asc'));
            $this->assertSame($column . '_desc', ListQuery::normalizeOrderSort($column . '_desc'));
        }
        $this->assertSame('date_asc', ListQuery::normalizeOrderSort('asc'));
        $this->assertSame('date_desc', ListQuery::normalizeOrderSort('desc'));
        $this->assertSame('date_desc', ListQuery::normalizeOrderSort(''));
        $this->assertSame('date_desc', ListQuery::normalizeOrderSort('price_asc'));
        $this->assertSame('date_desc', ListQuery::normalizeOrderSort('number_sideways'));
    }

    public function testParseSortSplitsAtTheLastUnderscore(): void
    {
        $allowed = ['name', 'purchase_price'];

        $this->assertSame(['purchase_price', 'desc'], ListQuery::parseSort('purchase_price_desc', $allowed, 'name_asc'));
        $this->assertSame(['name', 'asc'], ListQuery::parseSort('name_asc', $allowed, 'name_desc'));
        $this->assertSame(['name', 'desc'], ListQuery::parseSort('', $allowed, 'name_desc'));
    }
}
