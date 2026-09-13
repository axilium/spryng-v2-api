<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Exception;

/**
 * HTTP 404. The path is wrong, or the id does not exist on this account.
 */
final class NotFoundException extends ApiException
{
}
