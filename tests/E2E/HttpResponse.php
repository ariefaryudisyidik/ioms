<?php

declare(strict_types=1);

namespace Tests\E2E;

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $location,
        public readonly string $contentType,
    ) {
    }

    public function json(): array
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
