<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Resource;

use Acme\SpryngMessaging\Dto\Collection;
use Acme\SpryngMessaging\SpryngClient;

/**
 * Shared plumbing for the resource groups. Each subclass maps one section of
 * the portal documentation onto methods.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly SpryngClient $client)
    {
    }

    /**
     * The "data" node of a response, which is where every endpoint puts its
     * payload. Returns the whole body when there is no wrapper.
     *
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    protected function data(array $response): array
    {
        $data = $response['data'] ?? $response;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function collection(array $response): Collection
    {
        return Collection::fromResponse($response);
    }

    /**
     * Several endpoints want the account named in the body rather than in the
     * header. Fill it in from the client unless the caller already did.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function withAccountReference(array $payload): array
    {
        if (isset($payload['accountReference'])) {
            return $payload;
        }

        $accountReference = $this->client->accountReference();

        if ($accountReference === null) {
            return $payload;
        }

        return ['accountReference' => $accountReference] + $payload;
    }
}
