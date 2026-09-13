<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Exception;

/**
 * The request never reached Spryng: DNS failure, TLS problem, timeout.
 * Safe to retry.
 */
final class TransportException extends SpryngException
{
}
