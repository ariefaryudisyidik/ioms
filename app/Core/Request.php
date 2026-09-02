<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around superglobals + JSON body for the current HTTP request.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $server;

    /** @var array<string,mixed>|null */
    private ?array $jsonBody = null;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @param array<string,mixed> $server
     */
    public function __construct(array $query = [], array $body = [], array $server = [])
    {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $source = $this->isJson() ? $this->jsonBody() : $this->body;

        if ($key === null) {
            return $source;
        }

        return $source[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->isJson() ? $this->jsonBody() : $this->body);
    }

    public function isJson(): bool
    {
        $contentType = (string) ($this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? '');

        return str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        $this->jsonBody = is_array($decoded) ? $decoded : [];

        return $this->jsonBody;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }
}
