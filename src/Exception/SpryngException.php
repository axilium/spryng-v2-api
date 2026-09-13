<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Exception;

use RuntimeException;

/**
 * Base class for every exception thrown by this package. Catch this if you do
 * not care about the specific failure mode.
 */
class SpryngException extends RuntimeException
{
}
