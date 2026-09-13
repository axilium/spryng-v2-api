<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Dto;

use Acme\SpryngMessaging\Enum\Channel;
use Acme\SpryngMessaging\Enum\CharacterSet;
use Acme\SpryngMessaging\Enum\MessageType;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * An outbound message, as accepted by POST /v2/messages.
 *
 * Validates in the constructor so the mistakes that are cheap to catch locally
 * never cost an API call: a body that is neither text nor template, a mix of
 * recipients and address book contacts, a validity outside the allowed window.
 */
final class Message
{
    public const MAX_RECIPIENTS  = 50000;
    public const MAX_CONTACT_IDS = 10000;
    public const MAX_VALIDITY_HOURS = 72;

    /**
     * @param list<Recipient>                      $recipients Direct numbers. Mutually
     *        exclusive with $contactIds; exactly one of the two is required.
     * @param list<string>                         $contactIds Address book contact ids.
     * @param array<string, string|int|float|bool> $metaData Stored with the request
     *        and returned in the history export.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly array $recipients = [],
        public readonly ?string $text = null,
        public readonly ?string $templateId = null,
        public readonly ?string $from = null,
        public readonly array $contactIds = [],
        public readonly ?string $accountReference = null,
        public readonly Channel $channel = Channel::Sms,
        public readonly ?CharacterSet $characterSet = null,
        public readonly ?MessageType $messageType = null,
        public readonly ?string $name = null,
        public readonly ?DateTimeImmutable $validity = null,
        public readonly array $metaData = [],
    ) {
        $this->assertBody();
        $this->assertDestinations();
        $this->assertValidity();
    }

    /**
     * The common case: one text to one or more numbers.
     *
     * @param list<string>|string $numbers
     *
     * @throws InvalidArgumentException
     */
    public static function text(
        string|array $numbers,
        string $text,
        ?string $from = null,
        ?string $accountReference = null,
        ?MessageType $messageType = null,
        ?string $name = null,
    ): self {
        $recipients = array_map(
            static fn (string $number): Recipient => Recipient::parse($number),
            is_string($numbers) ? [$numbers] : array_values($numbers)
        );

        return new self(
            recipients:       $recipients,
            text:             $text,
            from:             $from,
            accountReference: $accountReference,
            messageType:      $messageType,
            name:             $name,
        );
    }

    /**
     * Returns a copy carrying the account reference, used by the client when
     * the message itself does not name one.
     */
    public function withAccountReference(string $accountReference): self
    {
        if ($this->accountReference === $accountReference) {
            return $this;
        }

        return new self(
            recipients:       $this->recipients,
            text:             $this->text,
            templateId:       $this->templateId,
            from:             $this->from,
            contactIds:       $this->contactIds,
            accountReference: $accountReference,
            channel:          $this->channel,
            characterSet:     $this->characterSet,
            messageType:      $this->messageType,
            name:             $this->name,
            validity:         $this->validity,
            metaData:         $this->metaData,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'accountReference' => $this->accountReference,
            'channel'          => $this->channel->value,
            'body'             => $this->text !== null
                ? ['text' => $this->text]
                : ['templateId' => $this->templateId],
        ];

        if ($this->from !== null) {
            $payload['from'] = $this->from;
        }

        if ($this->recipients !== []) {
            $payload['recipients'] = array_map(
                static fn (Recipient $recipient): array => $recipient->toPayload(),
                $this->recipients
            );
        }

        if ($this->contactIds !== []) {
            $payload['addressBook'] = [
                'contacts' => array_map(
                    static fn (string $id): array => ['id' => $id],
                    $this->contactIds
                ),
            ];
        }

        if ($this->name !== null) {
            $payload['name'] = $this->name;
        }

        if ($this->characterSet !== null) {
            $payload['characterSet'] = $this->characterSet->value;
        }

        if ($this->messageType !== null) {
            $payload['messageType'] = $this->messageType->value;
        }

        if ($this->validity !== null) {
            $payload['validity'] = self::formatTimestamp($this->validity);
        }

        if ($this->metaData !== []) {
            $payload['metaData'] = $this->metaData;
        }

        return $payload;
    }

    private function assertBody(): void
    {
        if ($this->text === null && $this->templateId === null) {
            throw new InvalidArgumentException('A message needs either text or a templateId.');
        }

        if ($this->text !== null && $this->templateId !== null) {
            throw new InvalidArgumentException('A message takes either text or a templateId, not both.');
        }

        if ($this->text !== null && trim($this->text) === '') {
            throw new InvalidArgumentException('Message text must not be empty.');
        }
    }

    private function assertDestinations(): void
    {
        if ($this->recipients === [] && $this->contactIds === []) {
            throw new InvalidArgumentException('A message needs either recipients or contactIds.');
        }

        if ($this->recipients !== [] && $this->contactIds !== []) {
            throw new InvalidArgumentException(
                'A message takes either recipients or contactIds, not both; the API rejects a request carrying both.'
            );
        }

        foreach ($this->recipients as $recipient) {
            if (!$recipient instanceof Recipient) {
                throw new InvalidArgumentException('Every entry in $recipients must be a Recipient.');
            }
        }

        if (count($this->recipients) > self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException(sprintf(
                'A request carries at most %d recipients, got %d.',
                self::MAX_RECIPIENTS,
                count($this->recipients)
            ));
        }

        if (count($this->contactIds) > self::MAX_CONTACT_IDS) {
            throw new InvalidArgumentException(sprintf(
                'A request carries at most %d contact ids, got %d.',
                self::MAX_CONTACT_IDS,
                count($this->contactIds)
            ));
        }
    }

    private function assertValidity(): void
    {
        if ($this->validity === null) {
            return;
        }

        $now = new DateTimeImmutable('now', $this->validity->getTimezone());

        if ($this->validity <= $now->modify('+1 minute')) {
            throw new InvalidArgumentException(
                'Validity must be more than a minute in the future; the API rejects anything sooner.'
            );
        }

        if ($this->validity > $now->modify(sprintf('+%d hours', self::MAX_VALIDITY_HOURS))) {
            throw new InvalidArgumentException(sprintf(
                'Validity must be at most %d hours in the future.',
                self::MAX_VALIDITY_HOURS
            ));
        }
    }

    /**
     * Formats a timestamp the way the API documents it: UTC, milliseconds,
     * trailing Z, e.g. 2026-07-19T09:07:12.190Z. The conversion matters -
     * stamping a local time with a Z would shift the moment.
     */
    public static function formatTimestamp(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }
}
