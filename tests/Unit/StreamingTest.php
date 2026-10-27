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
final class StreamingTest extends TestCase
{
    private const string KEY = 'whats the Elvish word for friend';

    private const string CONTEXT = 'BLAKE3 2019-12-27 16:29:52 test vectors context';

    #[DataProvider('streamingCases')]
    public function testStreamingMatchesOneShot(int $length, int $chunkSize): void
    {
        $input = TestData::input($length);
        $hasher = Hasher::new();

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $hasher->update(substr(string: $input, offset: $offset, length: $chunkSize));
        }

        static::assertSame(blake3_hash($input), $hasher->finalize());
    }

    public function testOneMiBStreamingMatchesOneShot(): void
    {
        $length = 1_048_576;
        $input = TestData::input($length);
        $hasher = Hasher::new();

        for ($offset = 0; $offset < $length; $offset += 4096) {
            $hasher->update(substr(string: $input, offset: $offset, length: 4096));
        }

        static::assertSame(blake3_hash($input), $hasher->finalize());
    }

    #[DataProvider('keyedAndDerivedStreamingCases')]
    public function testKeyedAndDerivedStreamingMatchesOneShot(string $mode, int $length, int $chunkSize): void
    {
        $input = TestData::input($length);
        $hasher = $mode === 'keyed' ? Hasher::newKeyed(self::KEY) : Hasher::newDeriveKey(self::CONTEXT);

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $hasher->update(substr(string: $input, offset: $offset, length: $chunkSize));
        }

        $expected = $mode === 'keyed'
            ? blake3_keyed_hash(key: self::KEY, data: $input)
            : blake3_derive_key(context: self::CONTEXT, keyMaterial: $input);

        static::assertSame($expected, $hasher->finalize());
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function keyedAndDerivedStreamingCases(): iterable
    {
        $lengths = [0, 1, 63, 64, 65, 1023, 1024, 1025, 2048, 102_400];
        $chunkSizes = [1, 63, 64, 65, 1024];

        foreach (['keyed', 'derive'] as $mode) {
            foreach ($lengths as $length) {
                foreach ($chunkSizes as $chunkSize) {
                    yield $mode . ' ' . $length . ' bytes in ' . $chunkSize . '-byte chunks' => [
                        $mode,
                        $length,
                        $chunkSize,
                    ];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function streamingCases(): iterable
    {
        $lengths = [
            0,
            1,
            63,
            64,
            65,
            127,
            128,
            129,
            1023,
            1024,
            1025,
            2048,
            2049,
            3072,
            4096,
            8192,
            31_744,
            102_400,
        ];
        $chunkSizes = [1, 2, 3, 7, 63, 64, 65, 127, 1023, 1024, 1025, 4096];

        foreach ($lengths as $length) {
            foreach ($chunkSizes as $chunkSize) {
                if ($length > 2049 && $chunkSize < 63) {
                    continue;
                }

                yield $length . ' bytes in ' . $chunkSize . '-byte chunks' => [$length, $chunkSize];
            }
        }
    }
}
