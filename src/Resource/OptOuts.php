<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Enum\Channel;
use Axilium\SpryngV2\Exception\SpryngException;

/**
 * Numbers that have opted out. Spryng blocks them itself, so this is for
 * keeping your own records in step and for adding opt-outs collected elsewhere.
 *
 * @see https://developer.spryng.nl/opt-outs/v2-optouts-create
 */
final class OptOuts extends AbstractResource
{
    /**
     * @param string $value The address to opt out, e.g. +31612345678.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function create(string $value, Channel $channel = Channel::Sms): array
    {
        return $this->data($this->client->post('/optouts', $this->withAccountReference([
            'channel' => $channel->value,
            'value'   => $value,
        ])));
    }

    /**
     * Documented filters: Address, Channel, StartTime, EndTime, SortBy,
     * SortOrder, OptOutMethod. Note the capitalised names.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/optouts', $filters));
    }

    /**
     * Removes an opt-out, which lets the number receive messages again. Only do
     * this with the consent of the recipient.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delete(string $value, Channel $channel = Channel::Sms): array
    {
        return $this->client->delete('/optouts', [
            'Value'   => $value,
            'Channel' => $channel->value,
        ]);
    }
}
