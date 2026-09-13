<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Dto\Message;
use Axilium\SpryngV2\Dto\SendResult;
use Axilium\SpryngV2\Exception\SpryngException;
use InvalidArgumentException;

/**
 * Outbound messages, the requests they belong to, the inbox and the URL
 * shortener metrics.
 *
 * @see https://developer.spryng.nl/messaging/v2-messages-create
 */
final class Messages extends AbstractResource
{
    /**
     * Sends a message. The API answers 202 Accepted: the request is queued, not
     * delivered, so the result carries ids rather than a delivery status.
     *
     * @throws InvalidArgumentException The message names no account reference
     *         and the client was constructed without one.
     * @throws SpryngException
     */
    public function send(Message $message): SendResult
    {
        return SendResult::fromResponse(
            $this->client->post('/messages', $this->withAccount($message)->toPayload())
        );
    }

    /**
     * One message by id, including its current delivery status.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function get(string $messageId): array
    {
        return $this->data($this->client->get('/messages/' . rawurlencode($messageId)));
    }

    /**
     * Sent and received messages, newest first by default.
     *
     * Documented filters: accountReference, requestId, conversationId, status,
     * reason, msisdn, destinationCode, originator, requestName, channel,
     * messageType, source, startTime, endTime, pageSize, pageNo, sortOrder,
     * sortBy, retrieveMessageBody, direction.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function list(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/messages', $filters));
    }

    /**
     * Inbound messages. Takes the same filters as list(), plus includeDelete.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function inbox(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/inbox', $filters));
    }

    /**
     * One request - the unit a single send() call creates - by id.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function getRequest(string $requestId): array
    {
        return $this->data($this->client->get('/requests/' . rawurlencode($requestId)));
    }

    /**
     * All requests.
     *
     * Documented filters: RequestId, Status, RequestReason, RequestedOriginator,
     * RequestName, Channel, MessageType, Source, StartTime, EndTime, PageSize,
     * PageNo, SortOrder, SortBy. Note the capitalised names; this endpoint
     * differs from list() in that respect.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function listRequests(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/requests', $filters));
    }

    /**
     * Every message belonging to one request, which is how you follow up a bulk
     * send: one call per request rather than one per recipient.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function listRequestMessages(string $requestId, array $filters = []): Collection
    {
        return $this->collection(
            $this->client->get('/requests/' . rawurlencode($requestId) . '/messages', $filters)
        );
    }

    /**
     * Click metrics for shortened URLs.
     *
     * Documented filters: pageSize, pageNo, sortOrder, sortBy, startTime, endTime.
     *
     * @param array<string, mixed> $filters
     *
     * @throws SpryngException
     */
    public function urlVisits(array $filters = []): Collection
    {
        return $this->collection($this->client->get('/url-shortener/visits', $filters));
    }

    /**
     * Click metrics for the shortened URLs in one request.
     *
     * @throws SpryngException
     */
    public function urlVisitsForRequest(string $requestId): Collection
    {
        return $this->collection(
            $this->client->get('/url-shortener/visits/' . rawurlencode($requestId))
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function withAccount(Message $message): Message
    {
        if ($message->accountReference !== null) {
            return $message;
        }

        $accountReference = $this->client->accountReference();

        if ($accountReference === null) {
            throw new InvalidArgumentException(
                'accountReference is required: pass it to the Message, or to the SpryngClient constructor.'
            );
        }

        return $message->withAccountReference($accountReference);
    }
}
