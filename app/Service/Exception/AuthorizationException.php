<?php

declare(strict_types=1);

namespace App\Service\Exception;

use RuntimeException;

final class AuthorizationException extends RuntimeException
{
    public static function forbidden(string $message = 'You are not authorized to perform this action.'): self
    {
        return new self($message);
    }
}
