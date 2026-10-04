<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedger;

interface StockLedgerRepositoryInterface
{
    public function record(StockLedger $entry): StockLedger;

    /**
     * @param array{product_id?:int,warehouse_id?:int,movement_type?:string,date_from?:string,date_to?:string,limit?:int,offset?:int} $filters
     * @return StockLedger[]
     */
    public function search(array $filters = []): array;
}
