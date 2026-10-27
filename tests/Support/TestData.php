<?php

declare(strict_types=1);

namespace StAllOne\Blake3\Tests\Support;

use RuntimeException;

/**
 * @phpstan-type Case array{input_len: int, hash: string, keyed_hash: string, derive_key: string}
 * @phpstan-type Vectors array{key: string, context_string: string, cases: list<Case>}
 */
final class TestData
{
    /** @var Vectors|null */
    private static ?array $vectors = null;

    /**
     * The official BLAKE3 test vectors use a repeating 251-byte pattern: 0, 1, ..., 250.
     */
    public static function input(int $length): string
    {
        $pattern = '';

        for ($i = 0; $i < 251; $i++) {
            $pattern .= chr($i);
        }

        $repetitions = intdiv(num1: $length, num2: 251) + 1;

        return substr(string: str_repeat(string: $pattern, times: $repetitions), offset: 0, length: $length);
    }

    /**
     * @return Vectors
     */
    public static function vectors(): array
    {
        if (self::$vectors !== null) {
            return self::$vectors;
        }

        $path = __DIR__ . '/../fixtures/test_vectors.json';
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("Unable to read BLAKE3 test vectors: {$path}");
        }

        /** @var Vectors $decoded */
        $decoded = json_decode(json: $json, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        self::$vectors = $decoded;

        return $decoded;
    }
}
