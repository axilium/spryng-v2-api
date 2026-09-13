<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Tests\Dto;

use Acme\SpryngMessaging\Dto\Message;
use Acme\SpryngMessaging\Dto\Recipient;
use Acme\SpryngMessaging\Enum\CharacterSet;
use Acme\SpryngMessaging\Enum\MessageType;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testItBuildsTheDocumentedPayload(): void
    {
        $message = new Message(
            recipients:       [new Recipient('+31612345678')],
            text:             'Your shift tomorrow is confirmed.',
            from:             'Acme',
            accountReference: 'SPNL0000000',
        );

        self::assertSame([
            'accountReference' => 'SPNL0000000',
            'channel'          => 'SMS',
            'body'             => ['text' => 'Your shift tomorrow is confirmed.'],
            'from'             => 'Acme',
            'recipients'       => [['msisdn' => '+31612345678']],
        ], $message->toPayload());
    }

    public function testItSendsOptionalFieldsOnlyWhenSet(): void
    {
        $validity = new DateTimeImmutable('+2 hours');

        $message = new Message(
            recipients:       [new Recipient('+31612345678')],
            text:             'Hello',
            accountReference: 'SPNL0000000',
            characterSet:     CharacterSet::Unicode,
            messageType:      MessageType::NotificationsAndReminders,
            name:             'shift-8842',
            validity:         $validity,
            metaData:         ['run' => 'nightly'],
        );

        $payload = $message->toPayload();

        self::assertSame('Unicode', $payload['characterSet']);
        self::assertSame('NotificationsAndReminders', $payload['messageType']);
        self::assertSame('shift-8842', $payload['name']);
        self::assertSame(['run' => 'nightly'], $payload['metaData']);
        self::assertSame(Message::formatTimestamp($validity), $payload['validity']);
        self::assertArrayNotHasKey('from', $payload);
    }

    public function testItSendsContactIdsAsAnAddressBook(): void
    {
        $message = new Message(
            text:             'Hello',
            contactIds:       ['486c5ddf-e047-49bf-a5ed-b3e3555f8843'],
            accountReference: 'SPNL0000000',
        );

        self::assertSame(
            ['contacts' => [['id' => '486c5ddf-e047-49bf-a5ed-b3e3555f8843']]],
            $message->toPayload()['addressBook']
        );
        self::assertArrayNotHasKey('recipients', $message->toPayload());
    }

    public function testItSendsATemplateIdInsteadOfText(): void
    {
        $message = new Message(
            recipients:       [new Recipient('+31612345678')],
            templateId:       '486c5ddf-e047-49bf-a5ed-b3e3555f8843',
            accountReference: 'SPNL0000000',
        );

        self::assertSame(
            ['templateId' => '486c5ddf-e047-49bf-a5ed-b3e3555f8843'],
            $message->toPayload()['body']
        );
    }

    public function testTextHelperParsesPlainNumbers(): void
    {
        $message = Message::text(['0031612345678', '+31687654321'], 'Hello', from: 'Acme');

        self::assertSame(
            [['msisdn' => '+31612345678'], ['msisdn' => '+31687654321']],
            array_map(static fn (Recipient $recipient): array => $recipient->toPayload(), $message->recipients)
        );
    }

    public function testItRejectsAMessageWithoutABody(): void
    {
        $this->expectExceptionMessage('either text or a templateId');

        new Message(recipients: [new Recipient('+31612345678')]);
    }

    public function testItRejectsBothTextAndTemplate(): void
    {
        $this->expectExceptionMessage('not both');

        new Message(
            recipients: [new Recipient('+31612345678')],
            text:       'Hello',
            templateId: '486c5ddf-e047-49bf-a5ed-b3e3555f8843',
        );
    }

    public function testItRejectsAMessageWithoutDestinations(): void
    {
        $this->expectExceptionMessage('either recipients or contactIds');

        new Message(text: 'Hello');
    }

    public function testItRejectsRecipientsAndContactIdsTogether(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Message(
            recipients: [new Recipient('+31612345678')],
            text:       'Hello',
            contactIds: ['486c5ddf-e047-49bf-a5ed-b3e3555f8843'],
        );
    }

    public function testItRejectsAValidityTooCloseToNow(): void
    {
        $this->expectExceptionMessage('more than a minute');

        new Message(
            recipients: [new Recipient('+31612345678')],
            text:       'Hello',
            validity:   new DateTimeImmutable('+10 seconds'),
        );
    }

    public function testItRejectsAValidityBeyondSeventyTwoHours(): void
    {
        $this->expectExceptionMessage('at most 72 hours');

        new Message(
            recipients: [new Recipient('+31612345678')],
            text:       'Hello',
            validity:   new DateTimeImmutable('+73 hours'),
        );
    }

    public function testItFormatsTimestampsAsUtcWithMilliseconds(): void
    {
        $moment = new DateTimeImmutable('2026-07-19 11:07:12.190', new DateTimeZone('Europe/Amsterdam'));

        self::assertSame('2026-07-19T09:07:12.190Z', Message::formatTimestamp($moment));
    }

    public function testWithAccountReferenceReturnsACopy(): void
    {
        $message = new Message(recipients: [new Recipient('+31612345678')], text: 'Hello');
        $stamped = $message->withAccountReference('SPNL0000000');

        self::assertNull($message->accountReference);
        self::assertSame('SPNL0000000', $stamped->accountReference);
        self::assertSame($stamped, $stamped->withAccountReference('SPNL0000000'));
    }
}
