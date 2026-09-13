<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Http;

use Axilium\SpryngV2\Exception\TransportException;

/**
 * Default transport, backed by ext-curl.
 */
final class CurlTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 10,
        private readonly int $connectTimeout = 5,
    ) {
    }

    public function request(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new TransportException('Could not initialise a cURL handle.');
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $formattedHeaders,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new TransportException(sprintf('cURL error %d: %s', $errno, $error), $errno);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new HttpResponse($status, (string) $raw, $responseHeaders);
    }
}
