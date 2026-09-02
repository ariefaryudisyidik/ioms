<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public static function forProduct(int $productId, int $requested, int $available): self
    {
        return new self(sprintf(
            'Insufficient stock for product #%d: requested %d, available %d.',
            $productId,
            $requested,
            $available
        ));
    }
}
