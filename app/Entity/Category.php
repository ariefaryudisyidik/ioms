<?php

declare(strict_types=1);

namespace App\Entity;

final class Category
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $description = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (string) $row['name'], $row['description'] ?? null);
    }
}
