<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Exception;

/**
 * HTTP 401 or 403. The API key is missing, expired, revoked, or not allowed on
 * the account reference you passed. Fix the credentials before retrying.
 */
final class AuthenticationException extends ApiException
{
}
