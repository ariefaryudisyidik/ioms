<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\PurchaseOrderService;
use App\Repository\InMemoryTransactionManager;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderDateValidationTest extends TestCase
{
    private function service(int $tolerance = 0): PurchaseOrderService
    {
        return new PurchaseOrderService(
            $this->createMock(\App\Repository\PurchaseOrderRepositoryInterface::class),
            $this->createMock(\App\Repository\ProductStockRepositoryInterface::class),
            $this->createMock(\App\Repository\StockLedgerRepositoryInterface::class),
            new InMemoryTransactionManager(),
            $tolerance,
        );
    }

    public function testEmptyDateIsRejected(): void
    {
        $this->assertNotNull($this->service()->validateOrderDate(''));
        $this->assertNotNull($this->service()->validateOrderDate(null));
    }

    public function testInvalidFormatIsRejected(): void
    {
        $this->assertNotNull($this->service()->validateOrderDate('31/12/2026'));
        $this->assertNotNull($this->service()->validateOrderDate('not-a-date'));
    }

    public function testTodayIsAccepted(): void
    {
        $today = date('Y-m-d');
        $this->assertNull($this->service()->validateOrderDate($today));
    }

    public function testFutureDateBeyondToleranceIsRejected(): void
    {
        $future = date('Y-m-d', strtotime('+5 days'));
        $this->assertNotNull($this->service(0)->validateOrderDate($future));
    }

    public function testFutureDateWithinToleranceIsAccepted(): void
    {
        $future = date('Y-m-d', strtotime('+2 days'));
        $this->assertNull($this->service(3)->validateOrderDate($future));
    }
}
