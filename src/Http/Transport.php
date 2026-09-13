<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Http;

use Axilium\SpryngV2\Exception\TransportException;

/**
 * Minimal HTTP abstraction. Deliberately not PSR-18: this package has no
 * external dependencies. The interface exists so tests can inject a fake and
 * so consumers can plug in their own client if they want to.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     *
     * @throws TransportException On a network-level failure (DNS, TLS, timeout).
     */
    public function request(string $method, string $url, array $headers, ?string $body): HttpResponse;
}
