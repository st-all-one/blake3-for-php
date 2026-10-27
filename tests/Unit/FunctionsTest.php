<?php

declare(strict_types=1);

namespace StAllOne\Blake3\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use StAllOne\Blake3\Hasher;
use ValueError;

use function StAllOne\Blake3\blake3_derive_key;
use function StAllOne\Blake3\blake3_hash;
use function StAllOne\Blake3\blake3_keyed_hash;

#[CoversClass(Hasher::class)]
#[CoversFunction('StAllOne\Blake3\blake3_hash')]
#[CoversFunction('StAllOne\Blake3\blake3_keyed_hash')]
#[CoversFunction('StAllOne\Blake3\blake3_derive_key')]
final class FunctionsTest extends TestCase
{
    private const string EMPTY_HASH = 'af1349b9f5f9a1a6a0404dea36dcc9499bcb25c9adc112b7cc9a93cae41f3262';

    private const string ABC_HASH = '6437b3ac38465133ffb63b75273a8db548c558465d79db03fd359c6cd5bd9d85';

    private const string EMPTY_KEYED = '92b2b75604ed3c761f9d6f62392c8a9227ad0ea3f09573e783f1498a4ed60d26';

    private const string EMPTY_DERIVED = '2cc39783c223154fea8dfb7c1b1660f2ac2dcbd1c1de8277b0b0dd39b7e50d7d';

    private const string KEY = 'whats the Elvish word for friend';

    private const string CONTEXT = 'BLAKE3 2019-12-27 16:29:52 test vectors context';

    public function testKnownVectors(): void
    {
        static::assertSame(self::EMPTY_HASH, blake3_hash(''));
        static::assertSame(self::ABC_HASH, blake3_hash('abc'));
        static::assertSame(self::EMPTY_KEYED, blake3_keyed_hash(key: self::KEY, data: ''));
        static::assertSame(self::EMPTY_DERIVED, blake3_derive_key(context: self::CONTEXT, keyMaterial: ''));
    }

    public function testDefaultOutputIsLowercaseHexadecimal(): void
    {
        $hash = blake3_hash('abc');

        static::assertSame(64, strlen($hash));
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function testRawOutput(): void
    {
        static::assertSame(self::ABC_HASH, bin2hex(blake3_hash(data: 'abc', rawOutput: true)));
        static::assertSame(self::EMPTY_KEYED, bin2hex(blake3_keyed_hash(key: self::KEY, data: '', rawOutput: true)));
        static::assertSame(
            self::EMPTY_DERIVED,
            bin2hex(blake3_derive_key(context: self::CONTEXT, keyMaterial: '', rawOutput: true)),
        );
    }

    public function testXofOutputIsPrefixConsistent(): void
    {
        $long = blake3_hash(data: 'abc', length: 1000);
        $lengths = [1, 2, 3, 4, 31, 32, 33, 63, 64, 65, 96, 127, 128, 129, 131, 160, 224, 255, 256, 1000];

        foreach ($lengths as $length) {
            static::assertSame(
                substr(string: $long, offset: 0, length: $length * 2),
                blake3_hash(data: 'abc', length: $length),
                'XOF length ' . $length,
            );
        }
    }

    public function testKeyedAndDerivedModesDifferFromPlainHash(): void
    {
        static::assertNotSame(blake3_hash('x'), blake3_keyed_hash(key: self::KEY, data: 'x'));
        static::assertNotSame(blake3_hash('x'), blake3_derive_key(context: self::CONTEXT, keyMaterial: 'x'));
        static::assertNotSame(
            blake3_derive_key(context: 'context-a', keyMaterial: 'x'),
            blake3_derive_key(context: 'context-b', keyMaterial: 'x'),
        );
    }

    public function testInvalidOutputLength(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('BLAKE3 output length must be at least 1 byte');

        blake3_hash(data: 'abc', length: 0);
    }

    public function testInvalidKeyLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('BLAKE3 keyed hash requires a 32-byte key');

        blake3_keyed_hash(key: 'short', data: '');
    }

    public function testKeyedKeyIsRedactedInExceptionTrace(): void
    {
        $secret = str_repeat(string: 'K', times: 32);

        try {
            blake3_keyed_hash(key: $secret, data: '', length: 0);
        } catch (ValueError $exception) {
            $args = $exception->getTrace()[1]['args'] ?? [];
            static::assertInstanceOf(\SensitiveParameterValue::class, $args[0] ?? null);

            return;
        }

        static::fail('Expected ValueError');
    }

    public function testDerivedKeyMaterialIsRedactedInExceptionTrace(): void
    {
        $secret = str_repeat(string: 'M', times: 32);

        try {
            blake3_derive_key(context: 'trace-context', keyMaterial: $secret, length: 0);
        } catch (ValueError $exception) {
            $args = $exception->getTrace()[1]['args'] ?? [];
            static::assertInstanceOf(\SensitiveParameterValue::class, $args[1] ?? null);

            return;
        }

        static::fail('Expected ValueError');
    }
}
