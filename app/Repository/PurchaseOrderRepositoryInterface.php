<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use PDO;

interface PurchaseOrderRepositoryInterface
{
    public function findById(int $id): ?PurchaseOrder;

    public function findWithItems(int $id): ?PurchaseOrder;

    public function poNumberExists(string $poNumber): bool;

    /**
     * @param array{status?:string,supplier_id?:int,warehouse_id?:int,date_from?:string,date_to?:string,limit?:int,offset?:int} $filters
     * @return PurchaseOrder[]
     */
    public function search(array $filters = []): array;

    public function countSearch(array $filters = []): int;

    public function save(PurchaseOrder $po): PurchaseOrder;

    public function saveItem(PurchaseOrderItem $item): PurchaseOrderItem;

    /** @return PurchaseOrderItem[] */
    public function itemsFor(int $poId): array;

    public function updateItemReceived(int $itemId, int $qtyReceived, ?PDO $pdo = null): void;

    public function updateStatus(int $poId, string $status, ?PDO $pdo = null): void;
}
