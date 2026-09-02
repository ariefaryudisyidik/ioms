<?php

declare(strict_types=1);

namespace App\Entity;

final class Customer
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $contact = null,
        public ?string $address = null,
        public bool $isActive = true,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            $row['contact'] ?? null,
            $row['address'] ?? null,
            (bool) $row['is_active'],
        );
    }
}
