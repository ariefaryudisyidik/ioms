<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlWarehouseRepository;

final class ApiController extends Controller
{
    /**
     * GET /api/products/{sku}/availability
     */
    public function productAvailability(Request $request, array $params): void
    {
        if (Auth::requireLoginApi()) {
            return;
        }

        $this->handle(function () use ($params) {
            $sku = (string) ($params['sku'] ?? '');

            $products = new MySqlProductRepository($this->pdo());
            $product = $products->findBySku($sku);

            if ($product === null) {
                $this->json(['error' => 'Not Found'], 404);

                return;
            }

            $stocks = new MySqlProductStockRepository($this->pdo());
            $warehouses = new MySqlWarehouseRepository($this->pdo());
            $rows = $stocks->findByProduct((int) $product->id);

            $warehouseList = [];
            $total = 0;
            foreach ($rows as $row) {
                $warehouse = $warehouses->findById($row->warehouseId);
                $warehouseList[] = [
                    'warehouse' => $warehouse?->name ?? ('#' . $row->warehouseId),
                    'quantity' => $row->quantity,
                ];
                $total += $row->quantity;
            }

            $this->json([
                'sku' => $product->sku,
                'name' => $product->name,
                'total' => $total,
                'warehouses' => $warehouseList,
            ], 200);
        }, true);
    }
}
