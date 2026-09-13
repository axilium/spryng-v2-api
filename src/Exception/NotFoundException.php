<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Exception;

/**
 * HTTP 404. The path is wrong, or the id does not exist on this account.
 */
final class NotFoundException extends ApiException
{
}
