<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;

interface PurchaseOrderRepositoryInterface
{
    public function findById(int $id): ?PurchaseOrder;

    public function findWithItems(int $id): ?PurchaseOrder;

    /**
     * Validation errors for references that do not exist or are inactive.
     *
     * @param array<int|string,int> $productIds
     * @return array<string,string> errors keyed by field ("supplier_id", "warehouse_id", "items.<index>")
     */
    public function invalidReferences(int $supplierId, int $warehouseId, array $productIds): array;

    /**
     * @param array{status?:string,supplier_id?:int,warehouse_id?:int,date_from?:string,date_to?:string,sort?:string,limit?:int,offset?:int} $filters
     * @return PurchaseOrder[]
     */
    public function search(array $filters = []): array;

    public function countSearch(array $filters = []): int;

    /** @return array<string,int> */
    public function countsByStatus(): array;

    public function save(PurchaseOrder $po): PurchaseOrder;

    public function saveItem(PurchaseOrderItem $item): PurchaseOrderItem;

    /** @return PurchaseOrderItem[] */
    public function itemsFor(int $poId): array;

    public function updateItemReceived(int $itemId, int $qtyReceived): void;

    public function updateStatus(int $poId, string $status): void;
}
