<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Tests;

use Acme\SpryngMessaging\Exception\ApiException;
use Acme\SpryngMessaging\Exception\AuthenticationException;
use Acme\SpryngMessaging\Exception\ConflictException;
use Acme\SpryngMessaging\Exception\NotFoundException;
use Acme\SpryngMessaging\Exception\RateLimitException;
use Acme\SpryngMessaging\Exception\ValidationException;
use Acme\SpryngMessaging\SpryngClient;
use Acme\SpryngMessaging\Tests\Double\FakeTransport;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpryngClientTest extends TestCase
{
    public function testItRejectsAnEmptyApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpryngClient('   ');
    }

    public function testItAuthenticatesWithAnApiKeyHeader(): void
    {
        $transport = FakeTransport::respondingWithJson(['data' => []]);

        (new SpryngClient('secret-key', 'SPNL0000000', $transport))->get('/balance');

        self::assertSame('secret-key', $transport->lastHeaders['X-Api-Key']);
        self::assertSame('SPNL0000000', $transport->lastHeaders['AccountReference']);
        self::assertSame('application/json', $transport->lastHeaders['Accept']);
        self::assertArrayNotHasKey('Authorization', $transport->lastHeaders);
        self::assertStringStartsWith('acme-spryng-messaging/', $transport->lastHeaders['User-Agent']);
    }

    public function testItOmitsTheAccountHeaderWhenNoneIsConfigured(): void
    {
        $transport = FakeTransport::respondingWithJson([]);

        (new SpryngClient('secret-key', transport: $transport))->get('/balance');

        self::assertArrayNotHasKey('AccountReference', $transport->lastHeaders);
    }

    public function testItSendsContentTypeOnlyWithABody(): void
    {
        $transport = FakeTransport::respondingWithJson([]);
        $client    = new SpryngClient('secret-key', transport: $transport);

        $client->get('/messages');
        self::assertArrayNotHasKey('Content-Type', $transport->lastHeaders);
        self::assertNull($transport->lastBody);

        $client->post('/messages', ['text' => 'Hello']);
        self::assertSame('application/json', $transport->lastHeaders['Content-Type']);
        self::assertSame('{"text":"Hello"}', $transport->lastBody);
    }

    public function testItTargetsTheDocumentedBaseUrl(): void
    {
        $transport = FakeTransport::respondingWithJson([]);

        (new SpryngClient('secret-key', transport: $transport))->get('/messages');

        self::assertSame('https://api.spryng.nl/v2/messages', $transport->lastUrl);
    }

    public function testItHonoursACustomBaseUrl(): void
    {
        $transport = FakeTransport::respondingWithJson([]);

        (new SpryngClient('secret-key', transport: $transport, baseUrl: 'https://sandbox.example.com/v2/'))
            ->get('/messages');

        self::assertSame('https://sandbox.example.com/v2/messages', $transport->lastUrl);
    }

    public function testItRepeatsQueryKeysForArrayFilters(): void
    {
        $transport = FakeTransport::respondingWithJson([]);

        (new SpryngClient('secret-key', transport: $transport))
            ->get('/contacts', ['contactId' => ['a', 'b'], 'search' => 'Ada Lovelace']);

        self::assertSame(
            'https://api.spryng.nl/v2/contacts?contactId=a&contactId=b&search=Ada%20Lovelace',
            $transport->lastUrl
        );
    }

    public function testItRendersBooleansAndDropsEmptyFilters(): void
    {
        $transport = FakeTransport::respondingWithJson([]);

        (new SpryngClient('secret-key', transport: $transport))->get('/messages', [
            'retrieveMessageBody' => true,
            'includeDelete'       => false,
            'status'              => null,
            'msisdn'              => [],
        ]);

        self::assertSame(
            'https://api.spryng.nl/v2/messages?retrieveMessageBody=true&includeDelete=false',
            $transport->lastUrl
        );
    }

    #[DataProvider('errorStatuses')]
    public function testItMapsStatusCodesToExceptions(int $status, string $expected): void
    {
        $transport = FakeTransport::respondingWith($status, (string) json_encode([
            'errors' => [['errorCode' => 'some_error', 'errorMessage' => 'Something went wrong']],
        ]));

        $this->expectException($expected);

        (new SpryngClient('secret-key', transport: $transport))->get('/messages');
    }

    /**
     * @return array<string, array{int, class-string}>
     */
    public static function errorStatuses(): array
    {
        return [
            '400 validation' => [400, ValidationException::class],
            '401 auth'       => [401, AuthenticationException::class],
            '403 auth'       => [403, AuthenticationException::class],
            '404 not found'  => [404, NotFoundException::class],
            '409 conflict'   => [409, ConflictException::class],
            '429 rate limit' => [429, RateLimitException::class],
            '500 api'        => [500, ApiException::class],
        ];
    }

    public function testItExposesTheErrorEnvelope(): void
    {
        $transport = FakeTransport::respondingWith(400, (string) json_encode([
            'errors' => [
                ['errorCode' => 'invalid_msisdn', 'errorMessage' => 'Recipient is not E.164'],
                ['errorCode' => 'missing_body', 'errorMessage' => 'Body is required'],
            ],
        ]));

        try {
            (new SpryngClient('secret-key', transport: $transport))->post('/messages', ['a' => 'b']);
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('invalid_msisdn', $exception->getErrorCode());
            self::assertCount(2, $exception->getErrors());
            self::assertSame('Recipient is not E.164', $exception->getErrors()[0]->message);
            self::assertSame(
                'invalid_msisdn: Recipient is not E.164 | missing_body: Body is required',
                $exception->getMessage()
            );
            self::assertArrayHasKey('errors', $exception->getBody());
            self::assertFalse($exception->isRetryable());
        }
    }

    public function testItFallsBackToAGenericMessageWithoutAnEnvelope(): void
    {
        $transport = FakeTransport::respondingWith(502, 'Bad Gateway');

        try {
            (new SpryngClient('secret-key', transport: $transport))->get('/messages');
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertSame('Unexpected response from the Spryng API (HTTP 502).', $exception->getMessage());
            self::assertTrue($exception->isRetryable());
            self::assertNull($exception->getErrorCode());
        }
    }

    public function testItReadsRetryAfterAndFallsBackToFiveSeconds(): void
    {
        $withHeader = FakeTransport::respondingWith(429, '{}', ['retry-after' => '30']);

        try {
            (new SpryngClient('secret-key', transport: $withHeader))->get('/messages');
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $exception) {
            self::assertSame(30, $exception->getRetryAfter());
            self::assertTrue($exception->isRetryable());
        }

        $withoutHeader = FakeTransport::respondingWith(429, '{}');

        try {
            (new SpryngClient('secret-key', transport: $withoutHeader))->get('/messages');
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $exception) {
            self::assertSame(RateLimitException::DEFAULT_RETRY_AFTER, $exception->getRetryAfter());
        }
    }

    public function testItAcceptsAnEmptyBodyOnNoContentResponses(): void
    {
        $transport = FakeTransport::respondingWith(204);

        self::assertSame([], (new SpryngClient('secret-key', transport: $transport))->delete('/templates/abc'));
        self::assertSame('DELETE', $transport->lastMethod);
        self::assertNull($transport->lastBody);
    }

    public function testItReusesResourceInstances(): void
    {
        $client = new SpryngClient('secret-key', transport: FakeTransport::respondingWithJson([]));

        self::assertSame($client->messages(), $client->messages());
        self::assertSame($client->contacts(), $client->contacts());
    }
}
