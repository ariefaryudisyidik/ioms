<?php

declare(strict_types=1);

namespace App\Entity;

final class Warehouse
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $location,
        public bool $isActive = true,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (string) $row['name'], (string) $row['location'], (bool) $row['is_active']);
    }
}
