<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Dto;

/**
 * What POST /v2/messages returns: 202 Accepted, not a delivered message.
 *
 *     {"data": {"requestId": "...", "messageIds": ["...", "..."]}}
 *
 * One request produces one requestId and one message id per recipient. Delivery
 * state is a separate question - poll Messages::get(), or subscribe a webhook.
 */
final class SendResult
{
    /**
     * @param list<string>         $messageIds One per recipient, in the order sent.
     * @param array<string, mixed> $raw        The full decoded response.
     */
    public function __construct(
        public readonly string $requestId,
        public readonly array $messageIds,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): self
    {
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;

        $messageIds = [];
        foreach ($data['messageIds'] ?? [] as $id) {
            $messageIds[] = (string) $id;
        }

        return new self(
            requestId:  isset($data['requestId']) ? (string) $data['requestId'] : '',
            messageIds: $messageIds,
            raw:        $response,
        );
    }

    /**
     * The single message id, for the common case of one recipient.
     */
    public function messageId(): ?string
    {
        return $this->messageIds[0] ?? null;
    }

    public function count(): int
    {
        return count($this->messageIds);
    }
}
