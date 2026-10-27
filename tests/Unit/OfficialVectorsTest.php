<?php

declare(strict_types=1);

namespace StAllOne\Blake3\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StAllOne\Blake3\Hasher;
use StAllOne\Blake3\Tests\Support\TestData;

use function StAllOne\Blake3\blake3_derive_key;
use function StAllOne\Blake3\blake3_hash;
use function StAllOne\Blake3\blake3_keyed_hash;

#[CoversClass(Hasher::class)]
#[CoversFunction('StAllOne\Blake3\blake3_hash')]
#[CoversFunction('StAllOne\Blake3\blake3_keyed_hash')]
#[CoversFunction('StAllOne\Blake3\blake3_derive_key')]
final class OfficialVectorsTest extends TestCase
{
    private const int EXTENDED_LENGTH = 131;

    private const int DEFAULT_LENGTH = 32;

    #[DataProvider('officialVectors')]
    public function testOfficialVectors(int $inputLength, string $hash, string $keyedHash, string $derivedKey): void
    {
        $vectors = TestData::vectors();
        $input = TestData::input($inputLength);

        static::assertSame($hash, blake3_hash(data: $input, length: self::EXTENDED_LENGTH));
        static::assertSame(
            substr(string: $hash, offset: 0, length: self::DEFAULT_LENGTH * 2),
            blake3_hash(data: $input),
        );
        static::assertSame($hash, bin2hex(blake3_hash(data: $input, length: self::EXTENDED_LENGTH, rawOutput: true)));

        static::assertSame($keyedHash, blake3_keyed_hash(
            key: $vectors['key'],
            data: $input,
            length: self::EXTENDED_LENGTH,
        ));
        static::assertSame(
            substr(string: $keyedHash, offset: 0, length: self::DEFAULT_LENGTH * 2),
            blake3_keyed_hash(key: $vectors['key'], data: $input),
        );
        static::assertSame(
            $keyedHash,
            bin2hex(blake3_keyed_hash(
                key: $vectors['key'],
                data: $input,
                length: self::EXTENDED_LENGTH,
                rawOutput: true,
            )),
        );

        static::assertSame($derivedKey, blake3_derive_key(
            context: $vectors['context_string'],
            keyMaterial: $input,
            length: self::EXTENDED_LENGTH,
        ));
        static::assertSame(
            substr(string: $derivedKey, offset: 0, length: self::DEFAULT_LENGTH * 2),
            blake3_derive_key(context: $vectors['context_string'], keyMaterial: $input),
        );
        static::assertSame(
            $derivedKey,
            bin2hex(blake3_derive_key(
                context: $vectors['context_string'],
                keyMaterial: $input,
                length: self::EXTENDED_LENGTH,
                rawOutput: true,
            )),
        );
    }

    /**
     * @return iterable<string, array{int, string, string, string}>
     */
    public static function officialVectors(): iterable
    {
        foreach (TestData::vectors()['cases'] as $case) {
            yield 'input length ' . $case['input_len'] => [
                $case['input_len'],
                $case['hash'],
                $case['keyed_hash'],
                $case['derive_key'],
            ];
        }
    }
}
