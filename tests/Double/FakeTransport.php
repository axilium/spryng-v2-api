<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tests\Double;

use Axilium\SpryngV2\Http\HttpResponse;
use Axilium\SpryngV2\Http\Transport;
use RuntimeException;

/**
 * Records outgoing requests and replays canned responses. No network.
 *
 * Queue several responses to walk a client through a sequence of calls; the
 * last one is repeated once the queue runs dry, so a test that only cares about
 * the request it sends does not have to queue anything twice.
 */
final class FakeTransport implements Transport
{
    public ?string $lastMethod = null;
    public ?string $lastUrl    = null;
    public ?string $lastBody   = null;

    /** @var array<string, string> */
    public array $lastHeaders = [];

    public int $callCount = 0;

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    /** @var list<HttpResponse> */
    private array $responses;

    public function __construct(HttpResponse ...$responses)
    {
        if ($responses === []) {
            $responses = [new HttpResponse(200, '{}')];
        }

        $this->responses = array_values($responses);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function respondingWith(int $status, string $body = '', array $headers = []): self
    {
        return new self(new HttpResponse($status, $body, $headers));
    }

    /**
     * @param array<mixed> $data
     */
    public static function respondingWithJson(array $data, int $status = 200): self
    {
        return new self(new HttpResponse($status, (string) json_encode($data)));
    }

    public function request(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $this->callCount++;
        $this->lastMethod  = $method;
        $this->lastUrl     = $url;
        $this->lastHeaders = $headers;
        $this->lastBody    = $body;
        $this->requests[]  = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return count($this->responses) > 1
            ? (array_shift($this->responses) ?? throw new RuntimeException('No response queued.'))
            : $this->responses[0];
    }

    /**
     * @return array<mixed>
     */
    public function decodedRequestBody(): array
    {
        $decoded = json_decode((string) $this->lastBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The path and query of the last request, without the base URL.
     */
    public function lastPath(): string
    {
        $path  = (string) parse_url((string) $this->lastUrl, PHP_URL_PATH);
        $query = parse_url((string) $this->lastUrl, PHP_URL_QUERY);

        return $query === null ? $path : $path . '?' . $query;
    }
}
