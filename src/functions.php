<?php

declare(strict_types=1);

namespace StAllOne\Blake3;

/**
 * Hash bytes with BLAKE3.
 *
 * @param string $data input bytes
 * @param int $length output length in bytes (XOF); at least 1
 * @param bool $rawOutput true for binary bytes, false for lowercase hexadecimal
 *
 * @throws \ValueError when $length is lower than 1
 */
function blake3_hash(string $data, int $length = 32, bool $rawOutput = false): string
{
    return Hasher::new()->update($data)->finalize($length, $rawOutput);
}

/**
 * Hash bytes with the BLAKE3 keyed mode.
 *
 * @param string $key exactly 32 bytes
 * @param string $data input bytes
 * @param int $length output length in bytes (XOF); at least 1
 * @param bool $rawOutput true for binary bytes, false for lowercase hexadecimal
 *
 * @throws \InvalidArgumentException when the key length is not 32 bytes
 * @throws \ValueError when $length is lower than 1
 */
function blake3_keyed_hash(
    #[\SensitiveParameter]
    string $key,
    string $data,
    int $length = 32,
    bool $rawOutput = false,
): string {
    return Hasher::newKeyed($key)->update($data)->finalize($length, $rawOutput);
}

/**
 * Derive a key from key material using a context string.
 *
 * @param string $context globally unique, application-specific context
 * @param string $keyMaterial input key material
 * @param int $length output length in bytes (XOF); at least 1
 * @param bool $rawOutput true for binary bytes, false for lowercase hexadecimal
 *
 * @throws \ValueError when $length is lower than 1
 */
function blake3_derive_key(
    string $context,
    #[\SensitiveParameter]
    string $keyMaterial,
    int $length = 32,
    bool $rawOutput = false,
): string {
    return Hasher::newDeriveKey($context)->update($keyMaterial)->finalize($length, $rawOutput);
}
