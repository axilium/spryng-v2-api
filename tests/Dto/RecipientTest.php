<?php

declare(strict_types=1);

namespace Axilium\SpryngV2\Tests\Dto;

use Axilium\SpryngV2\Dto\Recipient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecipientTest extends TestCase
{
    public function testItAcceptsAnE164Number(): void
    {
        $recipient = new Recipient('+31612345678');

        self::assertSame('+31612345678', $recipient->msisdn);
        self::assertSame(['msisdn' => '+31612345678'], $recipient->toPayload());
    }

    public function testItKeepsVariablesAndMetaDataOutOfThePayloadWhenEmpty(): void
    {
        $recipient = new Recipient('+31612345678', ['name' => 'Ada'], ['shift' => '8842']);

        self::assertSame([
            'msisdn'    => '+31612345678',
            'variables' => ['name' => 'Ada'],
            'metaData'  => ['shift' => '8842'],
        ], $recipient->toPayload());
    }

    #[DataProvider('malformedNumbers')]
    public function testItRejectsAnythingThatIsNotE164(string $number): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Recipient($number);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedNumbers(): array
    {
        return [
            'no plus'          => ['31612345678'],
            'national'         => ['0612345678'],
            'leading zero'     => ['+0612345678'],
            'too short'        => ['+3161'],
            'too long'         => ['+3161234567890123'],
            'letters'          => ['+316abcdefgh'],
            'empty'            => [''],
        ];
    }

    #[DataProvider('parseableNumbers')]
    public function testItParsesTheShapesPeopleActuallyStore(string $input, ?string $countryCode, string $expected): void
    {
        self::assertSame($expected, Recipient::parse($input, defaultCountryCode: $countryCode)->msisdn);
    }

    /**
     * @return array<string, array{string, string|null, string}>
     */
    public static function parseableNumbers(): array
    {
        return [
            'already E.164'      => ['+31612345678', null, '+31612345678'],
            'spaces and dashes'  => ['+31 6-1234 5678', null, '+31612345678'],
            'double zero prefix' => ['0031612345678', null, '+31612345678'],
            'no prefix at all'   => ['31612345678', null, '+31612345678'],
            'national with code' => ['0612345678', '31', '+31612345678'],
            'country code plus'  => ['0612345678', '+31', '+31612345678'],
        ];
    }

    public function testItRefusesANationalNumberWithoutACountry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Recipient::parse('0612345678');
    }
}
