<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Exception;

/**
 * HTTP 400. The request was understood but rejected: a missing required field,
 * a malformed number, an unknown enum value. Never retry it unchanged.
 *
 * Note that v2 uses 400 for this, not 422.
 */
final class ValidationException extends ApiException
{
}
