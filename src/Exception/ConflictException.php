<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Exception;

/**
 * HTTP 409. The request collides with something that already exists, such as a
 * contact with the same number or a template with the same name.
 */
final class ConflictException extends ApiException
{
}
