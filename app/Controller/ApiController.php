<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\ProductAvailabilityService;

final class ApiController extends Controller
{
    /**
     * GET /api/products/{sku}/availability
     */
    public function productAvailability(array $params): void
    {
        if (Auth::requireLoginApi()) {
            return;
        }

        $this->handle(function () use ($params) {
            $sku = (string) ($params['sku'] ?? '');

            $service = new ProductAvailabilityService(
                new MySqlProductRepository($this->pdo()),
                new MySqlProductStockRepository($this->pdo()),
                new MySqlWarehouseRepository($this->pdo()),
            );
            $availability = $service->forSku($sku);

            if ($availability === null) {
                $this->json(['error' => 'Not Found'], 404);

                return;
            }

            $this->json($availability, 200);
        }, true);
    }
}
