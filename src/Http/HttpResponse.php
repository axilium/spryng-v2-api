<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Http;

/**
 * Immutable value object representing a raw HTTP response.
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers Lowercased header names.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Decodes the body as an associative array. Returns an empty array when the
     * body is empty or not valid JSON, so callers never have to null-check.
     *
     * @return array<mixed>
     */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
