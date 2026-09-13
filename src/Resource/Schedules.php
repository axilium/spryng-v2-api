<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Dto\Message;
use Axilium\SpryngV2\Dto\Recipient;
use Axilium\SpryngV2\Exception\SpryngException;
use DateTimeInterface;

/**
 * Delayed sends: the same dispatch as an immediate message, but held until a
 * send time, optionally repeating.
 *
 * The payload splits into scheduleInformation (when), dispatch (what) and the
 * destinations (recipients, contacts or groups).
 *
 * @see https://developer.spryng.nl/schedule/v2-scheduled-messages-create
 */
final class Schedules extends AbstractResource
{
    /**
     * @param list<Recipient>|list<string> $recipients Direct numbers.
     * @param list<string>                 $contactIds Address book contacts.
     * @param list<string>                 $groupIds   Contact groups.
     * @param array<string, mixed>         $scheduleInformation Extra schedule
     *        fields next to sendTime: frequency, repeatTimes.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function create(
        Message $dispatch,
        DateTimeInterface $sendTime,
        array $recipients = [],
        array $contactIds = [],
        array $groupIds = [],
        array $scheduleInformation = [],
    ): array {
        $payload = [
            'scheduleInformation' => ['sendTime' => Message::formatTimestamp($sendTime)] + $scheduleInformation,
            'dispatch'            => $this->dispatch($dispatch),
        ];

        $recipients = $recipients === [] ? $dispatch->recipients : $recipients;
        $contactIds = $contactIds === [] ? $dispatch->contactIds : $contactIds;

        if ($recipients !== []) {
            $payload['recipients'] = array_map(
                static fn (Recipient|string $recipient): array => $recipient instanceof Recipient
                    ? $recipient->toPayload()
                    : Recipient::parse($recipient)->toPayload(),
                array_values($recipients)
            );
        }

        if ($contactIds !== []) {
            $payload['contacts'] = array_map(
                static fn (string $id): array => ['contactId' => $id],
                array_values($contactIds)
            );
        }

        if ($groupIds !== []) {
            $payload['groups'] = array_map(
                static fn (string $id): array => ['groupId' => $id],
                array_values($groupIds)
            );
        }

        return $this->data($this->client->post('/schedules/delayed', $payload));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(string $scheduleId): array
    {
        return $this->data($this->client->get('/schedules/delayed/' . rawurlencode($scheduleId)));
    }

    /**
     * Documented filters: DispatchName, StartCreatedDateTime, StartSendDateTime,
     * EndSendDateTime, StatusFilter, Msisdns, OrderBy.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/schedules/delayed', $filters));
    }

    /**
     * Partial update: send only what changes.
     *
     * @param array<string, mixed> $changes Keyed by scheduleInformation and
     *        dispatch, e.g. ['scheduleInformation' => ['sendTime' => '...']].
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function update(string $scheduleId, array $changes): array
    {
        return $this->data($this->client->patch(
            '/schedules/delayed/' . rawurlencode($scheduleId),
            $changes
        ));
    }

    /**
     * Reschedules to a new moment, leaving the dispatch untouched.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function reschedule(string $scheduleId, DateTimeInterface $sendTime): array
    {
        return $this->update($scheduleId, [
            'scheduleInformation' => ['sendTime' => Message::formatTimestamp($sendTime)],
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delete(string $scheduleId): array
    {
        return $this->client->delete('/schedules/delayed/' . rawurlencode($scheduleId));
    }

    /**
     * The dispatch node is a message without its destinations: those sit next
     * to it in the schedule payload rather than inside it.
     *
     * @return array<string, mixed>
     */
    private function dispatch(Message $message): array
    {
        $payload = $message->toPayload();

        unset($payload['recipients'], $payload['addressBook'], $payload['accountReference'], $payload['validity']);

        return $payload;
    }
}
