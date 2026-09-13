<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Exception;

/**
 * HTTP 429. Spryng applies one rate limit across the public endpoints and does
 * not publish the number. The documented remedy is to wait five seconds.
 */
final class RateLimitException extends ApiException
{
    public const DEFAULT_RETRY_AFTER = 5;

    /**
     * @param list<ApiError>       $errors
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $message,
        int $statusCode,
        array $errors = [],
        array $body = [],
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode, $errors, $body);
    }

    /**
     * Seconds to wait before retrying: the Retry-After header when the API
     * sends one, otherwise the five seconds the documentation prescribes.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter ?? self::DEFAULT_RETRY_AFTER;
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
