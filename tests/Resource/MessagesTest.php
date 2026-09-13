<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tests\Resource;

use Axilium\SpryngV2\Dto\Message;
use Axilium\SpryngV2\Dto\Recipient;
use Axilium\SpryngV2\SpryngClient;
use Axilium\SpryngV2\Tests\Double\FakeTransport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessagesTest extends TestCase
{
    public function testItPostsTheDocumentedSendPayload(): void
    {
        $transport = FakeTransport::respondingWithJson([
            'data' => [
                'requestId'  => '8dcad127-bb9c-48fe-9827-db2fe57d53fb',
                'messageIds' => ['0929df71-d1ea-4b02-87b2-9789b332a92e'],
            ],
        ], 202);

        $client = new SpryngClient('secret-key', 'SPNL0000000', $transport);
        $result = $client->messages()->send(Message::text('+31612345678', 'Hello', from: 'Acme'));

        self::assertSame('POST', $transport->lastMethod);
        self::assertSame('https://api.spryng.nl/v2/messages', $transport->lastUrl);
        self::assertSame([
            'accountReference' => 'SPNL0000000',
            'channel'          => 'SMS',
            'body'             => ['text' => 'Hello'],
            'from'             => 'Acme',
            'recipients'       => [['msisdn' => '+31612345678']],
        ], $transport->decodedRequestBody());

        self::assertSame('8dcad127-bb9c-48fe-9827-db2fe57d53fb', $result->requestId);
        self::assertSame('0929df71-d1ea-4b02-87b2-9789b332a92e', $result->messageId());
        self::assertSame(1, $result->count());
    }

    public function testItTakesTheAccountReferenceFromTheMessageWhenGiven(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['requestId' => 'r', 'messageIds' => []]], 202);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->messages()->send(new Message(
            recipients:       [new Recipient('+31612345678')],
            text:             'Hello',
            accountReference: 'SPNL9999999',
        ));

        self::assertSame('SPNL9999999', $transport->decodedRequestBody()['accountReference']);
    }

    public function testItRefusesToSendWithoutAnAccountReference(): void
    {
        $client = new SpryngClient('secret-key', transport: FakeTransport::respondingWithJson([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('accountReference is required');

        $client->messages()->send(Message::text('+31612345678', 'Hello'));
    }

    public function testItGetsOneMessage(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => ['messageId' => 'abc', 'status' => 'Delivered']]);

        $message = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->messages()->get('abc');

        self::assertSame('GET', $transport->lastMethod);
        self::assertSame('/v2/messages/abc', $transport->lastPath());
        self::assertSame(['messageId' => 'abc', 'status' => 'Delivered'], $message);
    }

    public function testItListsMessagesWithFilters(): void
    {
        $transport = FakeTransport::respondingWithJson([
            'data' => [
                'messages'    => [['messageId' => 'a'], ['messageId' => 'b']],
                'totalCount'  => 13191,
                'currentPage' => 1,
            ],
        ]);

        $messages = (new SpryngClient('secret-key', 'SPNL0000000', $transport))
            ->messages()
            ->list(['status' => 'Delivered', 'pageSize' => 50]);

        self::assertSame('/v2/messages?status=Delivered&pageSize=50', $transport->lastPath());
        self::assertCount(2, $messages);
        self::assertSame(13191, $messages->totalCount);
        self::assertSame(1, $messages->currentPage);
        self::assertSame(['messageId' => 'a'], $messages->first());
        self::assertSame(['a', 'b'], array_map(static fn (array $row): string => $row['messageId'], $messages->all()));
    }

    public function testItReachesTheRemainingReadEndpoints(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []]);
        $messages  = (new SpryngClient('secret-key', 'SPNL0000000', $transport))->messages();

        $messages->inbox(['pageNo' => 2]);
        self::assertSame('/v2/inbox?pageNo=2', $transport->lastPath());

        $messages->getRequest('req-1');
        self::assertSame('/v2/requests/req-1', $transport->lastPath());

        $messages->listRequests(['Status' => 'Completed']);
        self::assertSame('/v2/requests?Status=Completed', $transport->lastPath());

        $messages->listRequestMessages('req-1', ['PageSize' => 10]);
        self::assertSame('/v2/requests/req-1/messages?PageSize=10', $transport->lastPath());

        $messages->urlVisits(['pageSize' => 5]);
        self::assertSame('/v2/url-shortener/visits?pageSize=5', $transport->lastPath());

        $messages->urlVisitsForRequest('req-1');
        self::assertSame('/v2/url-shortener/visits/req-1', $transport->lastPath());

        self::assertSame(6, $transport->callCount);
    }

    public function testItEscapesIdsInThePath(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []]);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->messages()->get('a b/c');

        self::assertSame('/v2/messages/a%20b%2Fc', $transport->lastPath());
    }
}
