<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Exception\SpryngException;

/**
 * Contact groups, and the membership of those groups.
 *
 * @see https://developer.spryng.nl/groups/v2-groups-create
 */
final class Groups extends AbstractResource
{
    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function create(string $name, ?string $description = null): array
    {
        return $this->data($this->client->post('/groups', $this->withAccountReference(array_filter([
            'name'        => $name,
            'description' => $description,
        ], static fn (mixed $value): bool => $value !== null))));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(string $groupId): array
    {
        return $this->data($this->client->get('/groups/' . rawurlencode($groupId)));
    }

    /**
     * Documented filters: groupId, name, groupOwnership, sortBy, sortOrder.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/groups', $filters));
    }

    /**
     * @param array<string, mixed> $group Any of name, description.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function update(string $groupId, array $group): array
    {
        return $this->data($this->client->put('/groups/' . rawurlencode($groupId), $group));
    }

    /**
     * Deletes one or more groups. The contacts in them are not deleted.
     *
     * The prose documents this as DELETE /groups/{groupId} while the sample
     * uses ?groupId=; the sample is what this follows.
     *
     * @param list<string>|string $groupIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delete(array|string $groupIds): array
    {
        return $this->client->delete('/groups', [
            'groupId' => is_string($groupIds) ? [$groupIds] : array_values($groupIds),
        ]);
    }

    /**
     * @param list<string>|string $contactIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function addContacts(string $groupId, array|string $contactIds): array
    {
        return $this->data($this->client->put(
            '/groups/' . rawurlencode($groupId) . '/contacts',
            ['contactIds' => is_string($contactIds) ? [$contactIds] : array_values($contactIds)]
        ));
    }

    /**
     * Removes contacts from the group without deleting them.
     *
     * @param list<string>|string $contactIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function removeContacts(string $groupId, array|string $contactIds): array
    {
        return $this->client->delete('/groups/' . rawurlencode($groupId) . '/contacts', [
            'contactId' => is_string($contactIds) ? [$contactIds] : array_values($contactIds),
        ]);
    }

    /**
     * Documented filters: search, destination, contactOwnership, sortBy,
     * sortOrder, optOutStatus.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function contacts(string $groupId, array $filters = []): Collection
    {
        return $this->collection(
            $this->client->get('/groups/' . rawurlencode($groupId) . '/contacts', $filters)
        );
    }
}
