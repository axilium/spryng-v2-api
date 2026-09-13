<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Enum;

/**
 * How the body is encoded on the wire.
 *
 * - Gsm:     GSM 03.38, roughly 140 characters per message part.
 * - Unicode: needed for emoji and anything outside GSM 03.38, roughly 70
 *            characters per part, so it can double what a message costs.
 * - Auto:    Spryng picks GSM unless the text needs Unicode. Recommended.
 */
enum CharacterSet: string
{
    case Auto    = 'Auto';
    case Gsm     = 'GSM';
    case Unicode = 'Unicode';
}
