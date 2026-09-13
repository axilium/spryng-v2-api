<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Exception;

/**
 * One entry from the error envelope the API returns:
 *
 *     {"errors": [{"errorCode": "unauthenticatedError", "errorMessage": "..."}]}
 *
 * $code is the stable, machine-readable half and the one to branch on.
 * $message is prose meant for a log, not for an end user.
 */
final class ApiError
{
    /**
     * @param array<string, mixed> $raw The full entry, including any fields
     *                                  this class does not model.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<mixed> $data A decoded response body.
     *
     * @return list<self>
     */
    public static function listFromResponse(array $data): array
    {
        $errors = [];

        foreach ($data['errors'] ?? [] as $error) {
            if (!is_array($error)) {
                continue;
            }

            $errors[] = new self(
                code:    isset($error['errorCode']) ? (string) $error['errorCode'] : '',
                message: isset($error['errorMessage']) ? (string) $error['errorMessage'] : '',
                raw:     $error,
            );
        }

        return $errors;
    }

    public function __toString(): string
    {
        if ($this->code === '') {
            return $this->message;
        }

        return $this->message === '' ? $this->code : $this->code . ': ' . $this->message;
    }
}
