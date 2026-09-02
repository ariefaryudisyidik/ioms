<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use PDO;

interface SalesOrderRepositoryInterface
{
    public function findById(int $id): ?SalesOrder;

    public function findWithItems(int $id): ?SalesOrder;

    public function soNumberExists(string $soNumber): bool;

    /**
     * @param array{status?:string,customer_id?:int,warehouse_id?:int,created_by?:int,date_from?:string,date_to?:string,limit?:int,offset?:int} $filters
     * @return SalesOrder[]
     */
    public function search(array $filters = []): array;

    public function countSearch(array $filters = []): int;

    public function save(SalesOrder $so): SalesOrder;

    public function saveItem(SalesOrderItem $item): SalesOrderItem;

    /** @return SalesOrderItem[] */
    public function itemsFor(int $soId): array;

    public function updateStatus(int $soId, string $status, ?int $approvedBy = null, ?PDO $pdo = null): void;

    public function countByStatus(string $status): int;
}
