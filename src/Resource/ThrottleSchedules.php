<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Dto\Message;
use Axilium\SpryngV2\Dto\Recipient;
use Axilium\SpryngV2\Exception\SpryngException;

/**
 * Throttled sends: a large dispatch spread over time at a fixed rate, within a
 * daily time slot and on chosen days of the week. Use it to keep a bulk run
 * from arriving all at once, or to stay inside the hours a call centre is open.
 *
 * @see https://developer.spryng.nl/throttling/v2-scheduled-throttle-create
 */
final class ThrottleSchedules extends AbstractResource
{
    /**
     * @param array<string, mixed>         $throttleScheduleInformation startDate,
     *        endDate, timeSlotStart, timeSlotEnd, rateOfSend, daysOfWeek.
     * @param list<Recipient>|list<string> $recipients
     * @param list<string>                 $contactIds
     * @param list<string>                 $groupIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function create(
        Message $dispatch,
        array $throttleScheduleInformation,
        array $recipients = [],
        array $contactIds = [],
        array $groupIds = [],
    ): array {
        $payload = [
            'throttleScheduleInformation' => $throttleScheduleInformation,
            'dispatch'                    => $this->dispatch($dispatch),
        ] + $this->destinations(
            $recipients === [] ? $dispatch->recipients : $recipients,
            $contactIds === [] ? $dispatch->contactIds : $contactIds,
            $groupIds
        );

        return $this->data($this->client->post('/throttle-schedule', $payload));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(string $throttleScheduleId): array
    {
        return $this->data($this->client->get('/throttle-schedule/' . rawurlencode($throttleScheduleId)));
    }

    /**
     * Documented filters: FromStartDate, ToStartDate, FromCreatedDate,
     * ToCreatedDate, Status, msisdn, PageSize, PageNumber, OrderDir.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/throttle-schedule', $filters));
    }

    /**
     * The recipients that have not been dispatched yet, which is what you need
     * when a run is paused and you want to know what is still outstanding.
     *
     * @throws SpryngException
     */
    public function unsent(string $throttleScheduleId): Collection
    {
        return $this->collection(
            $this->client->get('/throttle-schedule/' . rawurlencode($throttleScheduleId) . '/unsent')
        );
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function update(string $throttleScheduleId, array $changes): array
    {
        return $this->data($this->client->patch(
            '/throttle-schedule/' . rawurlencode($throttleScheduleId),
            $changes
        ));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function delete(string $throttleScheduleId): array
    {
        return $this->client->delete('/throttle-schedule/' . rawurlencode($throttleScheduleId));
    }

    /**
     * Halts a running schedule. Everything already dispatched stays dispatched.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function pause(string $throttleScheduleId): array
    {
        return $this->client->post('/throttle-schedule/' . rawurlencode($throttleScheduleId) . '/pause');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function resume(string $throttleScheduleId): array
    {
        return $this->client->post('/throttle-schedule/' . rawurlencode($throttleScheduleId) . '/resume');
    }

    /**
     * Adds destinations to a schedule that is already running.
     *
     * @param list<Recipient>|list<string> $recipients
     * @param list<string>                 $contactIds
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function addRecipients(string $throttleScheduleId, array $recipients = [], array $contactIds = []): array
    {
        return $this->data($this->client->post(
            '/throttle-schedule/' . rawurlencode($throttleScheduleId) . '/recipients',
            $this->destinations($recipients, $contactIds, [])
        ));
    }

    /**
     * @param list<Recipient>|list<string> $recipients
     * @param list<string>                 $contactIds
     * @param list<string>                 $groupIds
     *
     * @return array<string, mixed>
     */
    private function destinations(array $recipients, array $contactIds, array $groupIds): array
    {
        $payload = [];

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

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatch(Message $message): array
    {
        $payload = $message->toPayload();

        unset($payload['recipients'], $payload['addressBook'], $payload['accountReference'], $payload['validity']);

        return $payload;
    }
}
