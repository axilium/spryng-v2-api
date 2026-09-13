<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Exception;

/**
 * The API answered, but with a non-2xx status.
 *
 * Not final: the subclasses below cover the statuses worth branching on, and
 * catching ApiException catches all of them.
 */
class ApiException extends SpryngException
{
    /**
     * @param list<ApiError>       $errors Parsed from the error envelope.
     * @param array<string, mixed> $body   The full decoded response body.
     */
    public function __construct(
        string $message,
        int $statusCode,
        private readonly array $errors = [],
        private readonly array $body = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    /**
     * @return list<ApiError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * The first error code, which is what you usually want to branch on.
     */
    public function getErrorCode(): ?string
    {
        return $this->errors[0]->code ?? null;
    }

    /**
     * The full decoded body, for the fields this package does not model.
     *
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * True for the failures where trying the exact same request again can
     * plausibly succeed.
     */
    public function isRetryable(): bool
    {
        return $this->getCode() >= 500;
    }
}
