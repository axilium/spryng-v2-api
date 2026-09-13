<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Resource;

use Acme\SpryngMessaging\Dto\Collection;
use Acme\SpryngMessaging\Exception\SpryngException;

/**
 * The address book.
 *
 * A contact carries firstName, lastName, quickName, a contactMetadata map and
 * one or more addresses, each of which is an addressType, an addressValue and
 * an optOutStatus. Contact payloads are passed and returned as arrays: the
 * portal documents them by example only, so a typed DTO would be guesswork.
 *
 * @see https://developer.spryng.nl/contacts/v2-contacts-create
 */
final class Contacts extends AbstractResource
{
    /**
     * @param array<string, mixed> $contact
     *
     * @return array<string, mixed> The created contact, including its id.
     *
     * @throws SpryngException
     */
    public function create(array $contact): array
    {
        return $this->data($this->client->post('/contacts', $this->withAccountReference($contact)));
    }

    /**
     * Creates up to a page of contacts in one call.
     *
     * @param list<array<string, mixed>> $contacts
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function createMany(array $contacts): array
    {
        return $this->data($this->client->post(
            '/contacts/bulk',
            $this->withAccountReference(['contacts' => array_values($contacts)])
        ));
    }

    /**
     * Creates the contacts that do not exist yet and updates the ones that do.
     *
     * Note the method: the portal prose calls this PUT while its own sample
     * uses PATCH. The sample is what this follows; see docs/api/CONFLICTS.md.
     *
     * @param list<array<string, mixed>> $contacts
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function createOrUpdateMany(array $contacts): array
    {
        return $this->data($this->client->patch(
            '/contacts/bulk',
            $this->withAccountReference(['contacts' => array_values($contacts)])
        ));
    }

    /**
     * @param array<string, mixed> $contact
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function update(string $contactId, array $contact): array
    {
        return $this->data($this->client->put('/contacts/' . rawurlencode($contactId), $contact));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(string $contactId): array
    {
        return $this->data($this->client->get('/contacts/' . rawurlencode($contactId)));
    }

    /**
     * Documented filters: optOutStatus, sortBy, sortOrder, search, destination,
     * groupId, contactOwnership.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/contacts/filter', $filters));
    }

    /**
     * Deletes one or more contacts. The ids go in the query string, repeated
     * per id: ?contactId=a&contactId=b.
     *
     * @param list<string>|string $contactIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delete(array|string $contactIds): array
    {
        return $this->client->delete('/contacts', [
            'contactId' => is_string($contactIds) ? [$contactIds] : array_values($contactIds),
        ]);
    }
}
