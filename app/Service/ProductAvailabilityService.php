<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\WarehouseRepositoryInterface;

/**
 * Stock availability of one product (by SKU), in total and per warehouse.
 */
final class ProductAvailabilityService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductStockRepositoryInterface $stocks,
        private WarehouseRepositoryInterface $warehouses,
    ) {
    }

    /**
     * @return array{sku:string,name:string,total:int,warehouses:list<array{warehouse:string,quantity:int}>}|null
     *         null when the SKU does not exist
     */
    public function forSku(string $sku): ?array
    {
        $product = $this->products->findBySku($sku);
        if ($product === null) {
            return null;
        }

        $perWarehouse = [];
        $total = 0;
        foreach ($this->stocks->findByProduct((int) $product->id) as $row) {
            $warehouse = $this->warehouses->findById($row->warehouseId);
            $perWarehouse[] = [
                'warehouse' => $warehouse?->name ?? ('#' . $row->warehouseId),
                'quantity' => $row->quantity,
            ];
            $total += $row->quantity;
        }

        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'total' => $total,
            'warehouses' => $perWarehouse,
        ];
    }
}
