<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

final class InvalidStatusTransitionException extends RuntimeException
{
    public static function make(string $from, string $to): self
    {
        return new self(sprintf('Invalid status transition from "%s" to "%s".', $from, $to));
    }
}
