<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Dto;

use InvalidArgumentException;

/**
 * One destination of an outbound message.
 *
 * v2 wants E.164 including the leading plus: +31612345678. That is the opposite
 * of the v1 API, which wanted the plus stripped, so numbers carried over from
 * an older integration need converting rather than copying.
 */
final class Recipient
{
    /**
     * @param array<string, string|int|float|bool> $variables Fills the [name]
     *        placeholders in the body, per recipient.
     * @param array<string, string|int|float|bool> $metaData Stored with the
     *        message and returned in the history export.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $msisdn,
        public readonly array $variables = [],
        public readonly array $metaData = [],
    ) {
        if (preg_match('/^\+[1-9]\d{6,14}$/', $this->msisdn) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Recipient "%s" is not in E.164 format; expected a leading plus and 7 to 15 digits, e.g. +31612345678.',
                $this->msisdn
            ));
        }
    }

    /**
     * Accepts the shapes people actually have in a database - "0031612345678",
     * "+31 6 12345678", "31612345678" - and normalises them to E.164.
     *
     * A national number such as "0612345678" cannot be normalised: without a
     * country there is nothing to prefix it with. Pass $defaultCountryCode to
     * say which country such numbers belong to.
     *
     * @param array<string, string|int|float|bool> $variables
     * @param array<string, string|int|float|bool> $metaData
     *
     * @throws InvalidArgumentException
     */
    public static function parse(
        string $number,
        array $variables = [],
        array $metaData = [],
        ?string $defaultCountryCode = null,
    ): self {
        $digits = (string) preg_replace('/[\s().-]/', '', trim($number));

        if (str_starts_with($digits, '00')) {
            $digits = '+' . substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && $defaultCountryCode !== null) {
            $digits = '+' . ltrim($defaultCountryCode, '+') . substr($digits, 1);
        }

        if (!str_starts_with($digits, '+') && preg_match('/^[1-9]\d{6,14}$/', $digits) === 1) {
            $digits = '+' . $digits;
        }

        return new self($digits, $variables, $metaData);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = ['msisdn' => $this->msisdn];

        if ($this->variables !== []) {
            $payload['variables'] = $this->variables;
        }

        if ($this->metaData !== []) {
            $payload['metaData'] = $this->metaData;
        }

        return $payload;
    }
}
