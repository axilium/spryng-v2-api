<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Dto;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A page of results.
 *
 * The list endpoints wrap their rows in a "data" node next to a total and a
 * page number, but the name of the row key differs per endpoint - "messages",
 * "contacts", "groups", "templates". The rows themselves are left as decoded
 * arrays: their fields come from example payloads in the documentation rather
 * than from a published schema, so pinning them into typed properties would
 * promise more than the documentation actually guarantees.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class Collection implements IteratorAggregate, Countable
{
    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $raw
     */
    public function __construct(
        public readonly array $items,
        public readonly ?int $totalCount = null,
        public readonly ?int $currentPage = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): self
    {
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;

        return new self(
            items:       self::extractItems($data),
            totalCount:  isset($data['totalCount']) ? (int) $data['totalCount'] : null,
            currentPage: isset($data['currentPage']) ? (int) $data['currentPage'] : null,
            raw:         $response,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function extractItems(array $data): array
    {
        // A plain list came back without a wrapper.
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        // Otherwise the rows sit under whichever key holds a list of objects:
        // "messages", "contacts", "groups", "templates", and so on.
        foreach ($data as $key => $value) {
            if (in_array($key, ['totalCount', 'currentPage', 'pageSize'], true)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                return array_values(array_filter($value, 'is_array'));
            }
        }

        return [];
    }
}
