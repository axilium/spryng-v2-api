<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Http;

use Axilium\SpryngV2\Exception\TransportException;

/**
 * Fallback transport for environments without ext-curl. Uses the HTTP stream
 * wrapper, which requires allow_url_fopen to be enabled.
 */
final class StreamTransport implements Transport
{
    public function __construct(private readonly int $timeout = 10)
    {
    }

    public function request(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        if (!filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            throw new TransportException('allow_url_fopen is disabled; use CurlTransport instead.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method'          => $method,
                'header'          => implode("\r\n", $headerLines),
                'content'         => $body ?? '',
                'timeout'         => $this->timeout,
                'ignore_errors'   => true,
                'follow_location' => 0,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            throw new TransportException(sprintf('Request to %s failed.', $url));
        }

        $status          = 0;
        $responseHeaders = [];

        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return new HttpResponse($status, $raw, $responseHeaders);
    }
}
