<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;

final class InMemorySalesOrderRepository implements SalesOrderRepositoryInterface
{
    /** @var SalesOrder[] */
    private array $orders = [];
    private int $nextId = 1;

    /** @var SalesOrderItem[] */
    private array $items = [];
    private int $nextItemId = 1;

    public function findById(int $id): ?SalesOrder
    {
        return $this->orders[$id] ?? null;
    }

    public function findWithItems(int $id): ?SalesOrder
    {
        $so = $this->findById($id);
        if ($so === null) {
            return null;
        }
        $so->items = $this->itemsFor($id);

        return $so;
    }

    public function invalidReferences(int $customerId, int $warehouseId, array $productIds): array
    {
        return [];
    }

    public function search(array $filters = []): array
    {
        $result = array_values($this->orders);
        if (!empty($filters['status'])) {
            $result = array_values(array_filter($result, fn ($o) => $o->status === $filters['status']));
        }
        if (!empty($filters['created_by'])) {
            $result = array_values(array_filter($result, fn ($o) => $o->createdBy === (int) $filters['created_by']));
        }

        return $result;
    }

    public function countSearch(array $filters = []): int
    {
        return count($this->search($filters));
    }

    public function save(SalesOrder $so): SalesOrder
    {
        if ($so->id === null) {
            $so->id = $this->nextId++;
        }
        $this->orders[$so->id] = $so;

        return $so;
    }

    public function saveItem(SalesOrderItem $item): SalesOrderItem
    {
        if ($item->id === null) {
            $item->id = $this->nextItemId++;
        }
        $this->items[$item->id] = $item;

        return $item;
    }

    public function itemsFor(int $soId): array
    {
        return array_values(array_filter($this->items, fn ($i) => $i->salesOrderId === $soId));
    }

    public function updateStatus(int $soId, string $status, ?int $approvedBy = null): void
    {
        if (isset($this->orders[$soId])) {
            $this->orders[$soId]->status = $status;
            if ($approvedBy !== null) {
                $this->orders[$soId]->approvedBy = $approvedBy;
            }
        }
    }

    public function countsByStatus(?int $createdBy = null): array
    {
        $counts = [];
        foreach ($this->orders as $o) {
            if ($createdBy !== null && $o->createdBy !== $createdBy) {
                continue;
            }
            $counts[$o->status] = ($counts[$o->status] ?? 0) + 1;
        }

        return $counts;
    }
}
