<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Shared validation of order line items (purchase and sales orders).
 */
final class OrderItemValidator
{
    public const MAX_QUANTITY = 1_000_000;
    public const MAX_PRICE = 1_000_000_000_000;

    /**
     * @return array<string,string> errors keyed by "items" or "items.<index>"
     */
    public static function validate(mixed $items, string $qtyKey, string $priceKey): array
    {
        if (!is_array($items) || count($items) === 0) {
            return ['items' => 'At least one item is required.'];
        }

        $errors = [];
        foreach ($items as $index => $item) {
            $message = self::itemError($item, $qtyKey, $priceKey);
            if ($message !== null) {
                $errors["items.$index"] = $message;
            }
        }

        return $errors;
    }

    /**
     * @param array<int|string,mixed> $items already validated items
     * @return array<int|string,int>
     */
    public static function productIds(array $items): array
    {
        return array_map(static fn ($item): int => (int) $item['product_id'], $items);
    }

    private static function itemError(mixed $item, string $qtyKey, string $priceKey): ?string
    {
        $hasProduct = is_array($item) && self::positiveInt($item['product_id'] ?? null, PHP_INT_MAX) !== null;
        if (!$hasProduct || self::positiveInt($item[$qtyKey] ?? null, self::MAX_QUANTITY) === null) {
            return 'Each item requires a product and a positive quantity.';
        }

        return self::isValidPrice($item[$priceKey] ?? '') ? null : 'Item price must be a non-negative number.';
    }

    private static function isValidPrice(mixed $price): bool
    {
        return $price === '' || $price === null
            || (is_numeric($price) && $price >= 0 && $price <= self::MAX_PRICE);
    }

    private static function positiveInt(mixed $value, int $max): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);

        return $int === false ? null : $int;
    }
}
