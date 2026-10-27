<?php

declare(strict_types=1);

namespace StAllOne\Blake3\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StAllOne\Blake3\Hasher;
use ValueError;

use function StAllOne\Blake3\blake3_hash;

#[CoversClass(Hasher::class)]
#[CoversFunction('StAllOne\Blake3\blake3_hash')]
final class HasherTest extends TestCase
{
    public function testFinalizeIsNonDestructive(): void
    {
        $hasher = Hasher::new();
        $hasher->update('a');

        $first = $hasher->finalize();

        static::assertSame($first, $hasher->finalize());
        static::assertSame($first, $hasher->finalize(rawOutput: false));
    }

    public function testUpdateAfterFinalizeContinuesTheStream(): void
    {
        $hasher = Hasher::new();
        $hasher->update('a');
        $hasher->finalize();
        $hasher->update('b');

        static::assertSame(blake3_hash('ab'), $hasher->finalize());
    }

    public function testEmptyUpdateIsANoOp(): void
    {
        static::assertSame(blake3_hash(''), Hasher::new()->update('')->finalize());
        static::assertSame(blake3_hash('x'), Hasher::new()->update('x')->update('')->finalize());
    }

    public function testRawOutputMatchesHexadecimalOutput(): void
    {
        $hasher = Hasher::new()->update('abc');

        static::assertSame(blake3_hash('abc'), $hasher->finalize());
        static::assertSame(blake3_hash(data: 'abc', rawOutput: true), $hasher->finalize(rawOutput: true));
    }

    public function testXofOutputIsPrefixConsistent(): void
    {
        $long = Hasher::new()->update('abc')->finalize(length: 1000);
        $lengths = [1, 2, 3, 4, 31, 32, 33, 63, 64, 65, 96, 127, 128, 129, 131, 160, 224, 255, 256, 1000];

        foreach ($lengths as $length) {
            static::assertSame(
                substr(string: $long, offset: 0, length: $length * 2),
                Hasher::new()->update('abc')->finalize(length: $length),
                'XOF length ' . $length,
            );
        }
    }

    #[DataProvider('invalidKeyLengths')]
    public function testKeyedRejectsWrongKeyLength(int $length): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('BLAKE3 keyed hash requires a 32-byte key');

        Hasher::newKeyed(str_repeat('k', $length));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidKeyLengths(): iterable
    {
        foreach ([0, 1, 31, 33, 64] as $length) {
            yield $length . ' bytes' => [$length];
        }
    }

    #[DataProvider('invalidOutputLengths')]
    public function testFinalizeRejectsNonPositiveLength(int $length): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('BLAKE3 output length must be at least 1 byte');

        Hasher::new()->finalize(length: $length);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidOutputLengths(): iterable
    {
        foreach ([0, -1, -32] as $length) {
            yield $length . ' bytes' => [$length];
        }
    }
}
