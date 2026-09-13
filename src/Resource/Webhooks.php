<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Resource;

use Axilium\SpryngV2\Dto\Collection;
use Axilium\SpryngV2\Exception\SpryngException;

/**
 * Webhook subscriptions: which events Spryng posts to which of your URLs, and
 * how it authenticates itself when it does.
 *
 * This package subscribes and unsubscribes. Receiving and verifying the
 * payloads is the other half of the job and deliberately out of scope, so the
 * package stays framework-agnostic.
 *
 * @see https://developer.spryng.nl/webhooks/v2-webhooks-create-subscription
 */
final class Webhooks extends AbstractResource
{
    /**
     * Subscribes to one or more events. The body is a list, so several events
     * can be set up in one call.
     *
     * @param list<array<string, mixed>> $subscriptions Each entry takes
     *        eventType, callbacks (a list of url, platform, optionalNotes),
     *        requiresAuthentication and contactEmail.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function subscribe(array $subscriptions): array
    {
        return $this->data($this->client->post('/webhooks/subscriptions', array_values($subscriptions)));
    }

    /**
     * Subscribes one event to one URL, the common case.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function subscribeUrl(
        string $eventType,
        string $url,
        bool $requiresAuthentication = false,
        ?string $contactEmail = null,
        ?string $platform = null,
    ): array {
        $subscription = [
            'eventType'              => $eventType,
            'callbacks'              => [array_filter(
                ['url' => $url, 'platform' => $platform],
                static fn (mixed $value): bool => $value !== null
            )],
            'requiresAuthentication' => $requiresAuthentication,
        ];

        if ($contactEmail !== null) {
            $subscription['contactEmail'] = $contactEmail;
        }

        return $this->subscribe([$subscription]);
    }

    /**
     * Every event type that can be subscribed to.
     *
     * @throws SpryngException
     */
    public function events(): Collection
    {
        return $this->collection($this->client->get('/webhooks/events'));
    }

    /**
     * Every subscription on the account, across all event types.
     *
     * GET /webhooks/subscriptions is missing from the portal documentation,
     * but the API serves it: the rows come back under data.events, each with
     * eventType, callbacks and requiresAuthentication.
     *
     * @throws SpryngException
     */
    public function subscriptions(): Collection
    {
        return $this->collection($this->client->get('/webhooks/subscriptions'));
    }

    /**
     * The subscriptions registered for one event type.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function subscriptionsFor(string $eventType): array
    {
        return $this->data($this->client->get('/webhooks/events/' . rawurlencode($eventType)));
    }

    /**
     * Replaces the callback URLs for one event type.
     *
     * @param list<array<string, mixed>>|list<string> $callbacks Either full
     *        callback objects or plain URLs.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function updateUrls(string $eventType, array $callbacks): array
    {
        $payload = array_map(
            static fn (array|string $callback): array => is_string($callback) ? ['url' => $callback] : $callback,
            array_values($callbacks)
        );

        return $this->data($this->client->put('/webhooks/events/' . rawurlencode($eventType), $payload));
    }

    /**
     * Sets how Spryng authenticates itself against your endpoint. Takes any of
     * an oauth, apiKey or basic node.
     *
     * @param array<string, mixed> $authentication
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function updateAuthentication(array $authentication): array
    {
        return $this->data($this->client->put('/webhooks/authentication-methods', $authentication));
    }

    /**
     * Unsubscribes from one event type.
     *
     * The prose calls the path parameter eventId while the sample uses
     * eventType; the sample is what this follows.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function unsubscribe(string $eventType): array
    {
        return $this->client->delete('/webhooks/events/' . rawurlencode($eventType));
    }

    /**
     * Removes every subscription on the account.
     *
     * @return array<string, mixed>
     *
     * @throws SpryngException
     */
    public function unsubscribeAll(): array
    {
        return $this->client->delete('/webhooks/subscriptions');
    }
}
