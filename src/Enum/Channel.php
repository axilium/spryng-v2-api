<?php

declare(strict_types=1);

namespace Acme\SpryngMessaging\Enum;

/**
 * Delivery channel. SMS is the only value the v2 documentation allows today.
 */
enum Channel: string
{
    case Sms = 'SMS';
}
