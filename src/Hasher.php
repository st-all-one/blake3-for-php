<?php

declare(strict_types=1);

namespace StAllOne\Blake3;

use InvalidArgumentException;
use ValueError;

/**
 * Incremental BLAKE3 hasher.
 *
 * The implementation mirrors the BLAKE3 reference implementation: one chunk state, a
 * stack of subtree chaining values, and a final ROOT compression that can emit an
 * arbitrary number of bytes (XOF).
 *
 * @phpstan-type Words list<int>
 * @phpstan-type Output array{cv: Words, block: Words, counter: int, blockLen: int, flags: int}
 */
final class Hasher
{
    private const int OUT_LEN = 32;

    private const int KEY_LEN = 32;

    private const int BLOCK_LEN = 64;

    private const int CHUNK_LEN = 1024;

    private const int CHUNK_START = 1;

    private const int CHUNK_END = 2;

    private const int PARENT = 4;

    private const int ROOT = 8;

    private const int KEYED_HASH = 16;

    private const int DERIVE_KEY_CONTEXT = 32;

    private const int DERIVE_KEY_MATERIAL = 64;

    private const int MASK = 0xFFFF_FFFF;

    /** @var Words */
    private const array IV = [
        0x6A09_E667,
        0xBB67_AE85,
        0x3C6E_F372,
        0xA54F_F53A,
        0x510E_527F,
        0x9B05_688C,
        0x1F83_D9AB,
        0x5BE0_CD19,
    ];

    /** @var Words */
    private array $cv;

    private int $chunkCounter = 0;

    private string $block = '';

    private int $blockLen = 0;

    private int $blocksCompressed = 0;

    /** @var list<Words> */
    private array $cvStack = [];

    /**
     * @param Words $keyWords
     */
    private function __construct(
        private readonly array $keyWords,
        private readonly int $flags,
    ) {
        $this->cv = $keyWords;
    }

    public static function new(): self
    {
        return new self(self::IV, 0);
    }

    /**
     * Start a keyed BLAKE3 hash. The key must be exactly 32 bytes.
     *
     * @throws InvalidArgumentException when the key length is not 32 bytes
     */
    public static function newKeyed(#[\SensitiveParameter] string $key): self
    {
        $keyLen = strlen($key);

        if ($keyLen !== self::KEY_LEN) {
            throw new InvalidArgumentException(sprintf(
                'BLAKE3 keyed hash requires a %d-byte key, %d given.',
                self::KEY_LEN,
                $keyLen,
            ));
        }

        return new self(self::words($key), self::KEYED_HASH);
    }

    /**
     * Start BLAKE3 key derivation. The context must be globally unique and application-specific.
     */
    public static function newDeriveKey(string $context): self
    {
        $contextHasher = new self(self::IV, self::DERIVE_KEY_CONTEXT);
        $contextHasher->update($context);
        $contextKey = $contextHasher->finalize(self::OUT_LEN, true);

        return new self(self::words($contextKey), self::DERIVE_KEY_MATERIAL);
    }

    /**
     * Add input bytes to the hash state.
     */
    public function update(string $input): self
    {
        $inputLen = strlen($input);
        $offset = 0;

        while ($offset < $inputLen) {
            if ($this->chunkLen() === self::CHUNK_LEN) {
                $chunkCv = $this->chunkChainingValue();
                $totalChunks = $this->chunkCounter + 1;
                $this->addChunkChainingValue($chunkCv, $totalChunks);
                $this->resetChunk($totalChunks);
            }

            $take = min(self::CHUNK_LEN - $this->chunkLen(), $inputLen - $offset);
            $this->updateChunk($input, $offset, $take);
            $offset += $take;
        }

        return $this;
    }

    /**
     * Produce an output of $length bytes. This method does not mutate the hasher, so it can
     * be called repeatedly and the hasher can still receive more input.
     *
     * @param int $length number of output bytes (XOF length), at least 1
     * @param bool $rawOutput true for binary bytes, false for lowercase hexadecimal
     *
     * @throws ValueError when $length is lower than 1
     */
    public function finalize(int $length = self::OUT_LEN, bool $rawOutput = false): string
    {
        if ($length < 1) {
            throw new ValueError('BLAKE3 output length must be at least 1 byte.');
        }

        $output = $this->chunkOutput();

        for ($i = count($this->cvStack) - 1; $i >= 0; $i--) {
            $output = self::parentOutput(
                $this->cvStack[$i],
                self::chainingValue($output),
                $this->keyWords,
                $this->flags,
            );
        }

        $bytes = self::rootOutputBytes($output, $length);

        return $rawOutput ? $bytes : bin2hex($bytes);
    }

    private function chunkLen(): int
    {
        return (self::BLOCK_LEN * $this->blocksCompressed) + $this->blockLen;
    }

    private function resetChunk(int $totalChunks): void
    {
        $this->cv = $this->keyWords;
        $this->chunkCounter = $totalChunks;
        $this->block = '';
        $this->blockLen = 0;
        $this->blocksCompressed = 0;
    }

    private function updateChunk(string $input, int $offset, int $length): void
    {
        $end = $offset + $length;

        while ($offset < $end) {
            if ($this->blockLen === self::BLOCK_LEN) {
                $this->compressBlock();
            }

            $take = min(self::BLOCK_LEN - $this->blockLen, $end - $offset);
            $this->block .= substr($input, $offset, $take);
            $this->blockLen += $take;
            $offset += $take;
        }
    }

    private function compressBlock(): void
    {
        $startFlag = $this->blocksCompressed === 0 ? self::CHUNK_START : 0;

        $this->cv = self::firstEightWords(self::compress(
            $this->cv,
            self::blockWords($this->block),
            $this->chunkCounter,
            self::BLOCK_LEN,
            $this->flags | $startFlag,
        ));

        $this->blocksCompressed++;
        $this->block = '';
        $this->blockLen = 0;
    }

    /**
     * Chaining value of a full chunk. Its last block is always buffered at 64 bytes and
     * no CHUNK_START flag applies because a full chunk has 15 preceding blocks.
     *
     * @return Words
     */
    private function chunkChainingValue(): array
    {
        assert(
            $this->blocksCompressed === 15 && $this->blockLen === self::BLOCK_LEN,
            description: 'A full chunk has 15 compressed blocks and one buffered 64-byte block.',
        );

        return self::firstEightWords(self::compress(
            $this->cv,
            self::blockWords($this->block),
            $this->chunkCounter,
            self::BLOCK_LEN,
            $this->flags | self::CHUNK_END,
        ));
    }

    /**
     * @return Output
     */
    private function chunkOutput(): array
    {
        $blockBytes = $this->blockLen === self::BLOCK_LEN
            ? $this->block
            : str_pad(string: $this->block, length: self::BLOCK_LEN, pad_string: "\0");

        return [
            'cv' => $this->cv,
            'block' => self::blockWords($blockBytes),
            'counter' => $this->chunkCounter,
            'blockLen' => $this->blockLen,
            'flags' => $this->flags | ($this->blocksCompressed === 0 ? self::CHUNK_START : 0) | self::CHUNK_END,
        ];
    }

    /**
     * @param Words $newCv
     */
    private function addChunkChainingValue(array $newCv, int $totalChunks): void
    {
        while (($totalChunks & 1) === 0) {
            $leftCv = array_pop($this->cvStack);

            assert(
                $leftCv !== null,
                description: 'The CV stack must contain the left child for every completed subtree.',
            );

            $newCv = self::parentChainingValue($leftCv, $newCv, $this->keyWords, $this->flags);
            $totalChunks >>= 1;
        }

        $this->cvStack[] = $newCv;
    }

    /**
     * @param Output $output
     *
     * @return Words
     */
    private static function chainingValue(array $output): array
    {
        return self::firstEightWords(self::compress(
            $output['cv'],
            $output['block'],
            $output['counter'],
            $output['blockLen'],
            $output['flags'],
        ));
    }

    /**
     * @param Words $words
     *
     * @return Words
     */
    private static function firstEightWords(array $words): array
    {
        return [
            $words[0],
            $words[1],
            $words[2],
            $words[3],
            $words[4],
            $words[5],
            $words[6],
            $words[7],
        ];
    }

    /**
     * @param Words $leftChildCv
     * @param Words $rightChildCv
     * @param Words $keyWords
     *
     * @return Output
     */
    private static function parentOutput(array $leftChildCv, array $rightChildCv, array $keyWords, int $flags): array
    {
        return [
            'cv' => $keyWords,
            'block' => [...$leftChildCv, ...$rightChildCv],
            'counter' => 0,
            'blockLen' => self::BLOCK_LEN,
            'flags' => self::PARENT | $flags,
        ];
    }

    /**
     * @param Words $leftChildCv
     * @param Words $rightChildCv
     * @param Words $keyWords
     *
     * @return Words
     */
    private static function parentChainingValue(
        array $leftChildCv,
        array $rightChildCv,
        array $keyWords,
        int $flags,
    ): array {
        return self::firstEightWords(self::compress(
            $keyWords,
            [...$leftChildCv, ...$rightChildCv],
            0,
            self::BLOCK_LEN,
            self::PARENT | $flags,
        ));
    }

    /**
     * @param Output $output
     */
    private static function rootOutputBytes(array $output, int $length): string
    {
        $outputBlockCounter = 0;
        $remaining = $length;
        $bytes = '';

        while ($remaining > 0) {
            $words = self::compress(
                $output['cv'],
                $output['block'],
                $outputBlockCounter,
                $output['blockLen'],
                $output['flags'] | self::ROOT,
            );

            $take = min(self::BLOCK_LEN, $remaining);

            if ($take === self::OUT_LEN) {
                // Default 32-byte output: only the first half of the root block is needed.
                $bytes .= pack(
                    'V8',
                    $words[0],
                    $words[1],
                    $words[2],
                    $words[3],
                    $words[4],
                    $words[5],
                    $words[6],
                    $words[7],
                );
                $remaining -= $take;
                $outputBlockCounter++;
                continue;
            }

            $bytes .= substr(string: pack('V*', ...$words), offset: 0, length: $take);
            $remaining -= $take;
            $outputBlockCounter++;
        }

        return $bytes;
    }

    /**
     * BLAKE3 compression with the state and message held in local variables.
     *
     * The eight G mixes of each round are inlined, eliminating 56 method calls and the
     * array read-modify-write traffic per block. The message permutation is applied
     * in place with two 8-cycles, avoiding a 16-element allocation per round. The
     * resulting method is intentionally large: splitting the rounds reintroduces the
     * call overhead this function exists to remove.
     *
     * @mago-expect lint:halstead
     *
     * @param Words $chainingValue
     * @param Words $blockWords
     *
     * @return Words
     */
    private static function compress(
        array $chainingValue,
        array $blockWords,
        int $counter,
        int $blockLen,
        int $flags,
    ): array {
        $cv0 = $chainingValue[0];
        $cv1 = $chainingValue[1];
        $cv2 = $chainingValue[2];
        $cv3 = $chainingValue[3];
        $cv4 = $chainingValue[4];
        $cv5 = $chainingValue[5];
        $cv6 = $chainingValue[6];
        $cv7 = $chainingValue[7];

        $v0 = $cv0;
        $v1 = $cv1;
        $v2 = $cv2;
        $v3 = $cv3;
        $v4 = $cv4;
        $v5 = $cv5;
        $v6 = $cv6;
        $v7 = $cv7;
        $v8 = self::IV[0];
        $v9 = self::IV[1];
        $v10 = self::IV[2];
        $v11 = self::IV[3];
        $v12 = $counter & self::MASK;
        $v13 = ($counter >> 32) & self::MASK;
        $v14 = $blockLen;
        $v15 = $flags;

        $m0 = $blockWords[0];
        $m1 = $blockWords[1];
        $m2 = $blockWords[2];
        $m3 = $blockWords[3];
        $m4 = $blockWords[4];
        $m5 = $blockWords[5];
        $m6 = $blockWords[6];
        $m7 = $blockWords[7];
        $m8 = $blockWords[8];
        $m9 = $blockWords[9];
        $m10 = $blockWords[10];
        $m11 = $blockWords[11];
        $m12 = $blockWords[12];
        $m13 = $blockWords[13];
        $m14 = $blockWords[14];
        $m15 = $blockWords[15];

        for ($round = 0; $round < 7; $round++) {
            // G(0, 4, 8, 12, m0, m1)
            $v0 = ($v0 + $v4 + $m0) & self::MASK;
            $v12 ^= $v0;
            $v12 = (($v12 >> 16) | ($v12 << 16)) & self::MASK;
            $v8 = ($v8 + $v12) & self::MASK;
            $v4 ^= $v8;
            $v4 = (($v4 >> 12) | ($v4 << 20)) & self::MASK;
            $v0 = ($v0 + $v4 + $m1) & self::MASK;
            $v12 ^= $v0;
            $v12 = (($v12 >> 8) | ($v12 << 24)) & self::MASK;
            $v8 = ($v8 + $v12) & self::MASK;
            $v4 ^= $v8;
            $v4 = (($v4 >> 7) | ($v4 << 25)) & self::MASK;

            // G(1, 5, 9, 13, m2, m3)
            $v1 = ($v1 + $v5 + $m2) & self::MASK;
            $v13 ^= $v1;
            $v13 = (($v13 >> 16) | ($v13 << 16)) & self::MASK;
            $v9 = ($v9 + $v13) & self::MASK;
            $v5 ^= $v9;
            $v5 = (($v5 >> 12) | ($v5 << 20)) & self::MASK;
            $v1 = ($v1 + $v5 + $m3) & self::MASK;
            $v13 ^= $v1;
            $v13 = (($v13 >> 8) | ($v13 << 24)) & self::MASK;
            $v9 = ($v9 + $v13) & self::MASK;
            $v5 ^= $v9;
            $v5 = (($v5 >> 7) | ($v5 << 25)) & self::MASK;

            // G(2, 6, 10, 14, m4, m5)
            $v2 = ($v2 + $v6 + $m4) & self::MASK;
            $v14 ^= $v2;
            $v14 = (($v14 >> 16) | ($v14 << 16)) & self::MASK;
            $v10 = ($v10 + $v14) & self::MASK;
            $v6 ^= $v10;
            $v6 = (($v6 >> 12) | ($v6 << 20)) & self::MASK;
            $v2 = ($v2 + $v6 + $m5) & self::MASK;
            $v14 ^= $v2;
            $v14 = (($v14 >> 8) | ($v14 << 24)) & self::MASK;
            $v10 = ($v10 + $v14) & self::MASK;
            $v6 ^= $v10;
            $v6 = (($v6 >> 7) | ($v6 << 25)) & self::MASK;

            // G(3, 7, 11, 15, m6, m7)
            $v3 = ($v3 + $v7 + $m6) & self::MASK;
            $v15 ^= $v3;
            $v15 = (($v15 >> 16) | ($v15 << 16)) & self::MASK;
            $v11 = ($v11 + $v15) & self::MASK;
            $v7 ^= $v11;
            $v7 = (($v7 >> 12) | ($v7 << 20)) & self::MASK;
            $v3 = ($v3 + $v7 + $m7) & self::MASK;
            $v15 ^= $v3;
            $v15 = (($v15 >> 8) | ($v15 << 24)) & self::MASK;
            $v11 = ($v11 + $v15) & self::MASK;
            $v7 ^= $v11;
            $v7 = (($v7 >> 7) | ($v7 << 25)) & self::MASK;

            // G(0, 5, 10, 15, m8, m9)
            $v0 = ($v0 + $v5 + $m8) & self::MASK;
            $v15 ^= $v0;
            $v15 = (($v15 >> 16) | ($v15 << 16)) & self::MASK;
            $v10 = ($v10 + $v15) & self::MASK;
            $v5 ^= $v10;
            $v5 = (($v5 >> 12) | ($v5 << 20)) & self::MASK;
            $v0 = ($v0 + $v5 + $m9) & self::MASK;
            $v15 ^= $v0;
            $v15 = (($v15 >> 8) | ($v15 << 24)) & self::MASK;
            $v10 = ($v10 + $v15) & self::MASK;
            $v5 ^= $v10;
            $v5 = (($v5 >> 7) | ($v5 << 25)) & self::MASK;

            // G(1, 6, 11, 12, m10, m11)
            $v1 = ($v1 + $v6 + $m10) & self::MASK;
            $v12 ^= $v1;
            $v12 = (($v12 >> 16) | ($v12 << 16)) & self::MASK;
            $v11 = ($v11 + $v12) & self::MASK;
            $v6 ^= $v11;
            $v6 = (($v6 >> 12) | ($v6 << 20)) & self::MASK;
            $v1 = ($v1 + $v6 + $m11) & self::MASK;
            $v12 ^= $v1;
            $v12 = (($v12 >> 8) | ($v12 << 24)) & self::MASK;
            $v11 = ($v11 + $v12) & self::MASK;
            $v6 ^= $v11;
            $v6 = (($v6 >> 7) | ($v6 << 25)) & self::MASK;

            // G(2, 7, 8, 13, m12, m13)
            $v2 = ($v2 + $v7 + $m12) & self::MASK;
            $v13 ^= $v2;
            $v13 = (($v13 >> 16) | ($v13 << 16)) & self::MASK;
            $v8 = ($v8 + $v13) & self::MASK;
            $v7 ^= $v8;
            $v7 = (($v7 >> 12) | ($v7 << 20)) & self::MASK;
            $v2 = ($v2 + $v7 + $m13) & self::MASK;
            $v13 ^= $v2;
            $v13 = (($v13 >> 8) | ($v13 << 24)) & self::MASK;
            $v8 = ($v8 + $v13) & self::MASK;
            $v7 ^= $v8;
            $v7 = (($v7 >> 7) | ($v7 << 25)) & self::MASK;

            // G(3, 4, 9, 14, m14, m15)
            $v3 = ($v3 + $v4 + $m14) & self::MASK;
            $v14 ^= $v3;
            $v14 = (($v14 >> 16) | ($v14 << 16)) & self::MASK;
            $v9 = ($v9 + $v14) & self::MASK;
            $v4 ^= $v9;
            $v4 = (($v4 >> 12) | ($v4 << 20)) & self::MASK;
            $v3 = ($v3 + $v4 + $m15) & self::MASK;
            $v14 ^= $v3;
            $v14 = (($v14 >> 8) | ($v14 << 24)) & self::MASK;
            $v9 = ($v9 + $v14) & self::MASK;
            $v4 ^= $v9;
            $v4 = (($v4 >> 7) | ($v4 << 25)) & self::MASK;

            if ($round === 6) {
                break;
            }

            // new message = [m2, m6, m3, m10, m7, m0, m4, m13,
            //                 m1, m11, m12, m5, m9, m14, m15, m8].
            // Rotate its two 8-cycles in place instead of allocating an array.
            $t = $m2;
            $m2 = $m3;
            $m3 = $m10;
            $m10 = $m12;
            $m12 = $m9;
            $m9 = $m11;
            $m11 = $m5;
            $m5 = $m0;
            $m0 = $t;

            $t = $m6;
            $m6 = $m4;
            $m4 = $m7;
            $m7 = $m13;
            $m13 = $m14;
            $m14 = $m15;
            $m15 = $m8;
            $m8 = $m1;
            $m1 = $t;
        }

        return [
            $v0 ^ $v8,
            $v1 ^ $v9,
            $v2 ^ $v10,
            $v3 ^ $v11,
            $v4 ^ $v12,
            $v5 ^ $v13,
            $v6 ^ $v14,
            $v7 ^ $v15,
            $v8 ^ $cv0,
            $v9 ^ $cv1,
            $v10 ^ $cv2,
            $v11 ^ $cv3,
            $v12 ^ $cv4,
            $v13 ^ $cv5,
            $v14 ^ $cv6,
            $v15 ^ $cv7,
        ];
    }

    /**
     * @return Words
     */
    private static function words(#[\SensitiveParameter] string $bytes): array
    {
        $unpacked = unpack('V*', $bytes);
        assert($unpacked !== false, description: 'unpack() must return words for the V* format.');

        /** @var Words $words */
        $words = array_values($unpacked);
        assert($words !== [], description: 'BLAKE3 words must not be empty.');

        return $words;
    }

    /**
     * @return Words
     */
    private static function blockWords(string $block): array
    {
        $unpacked = unpack('V16', $block);
        assert($unpacked !== false, description: 'unpack() must return words for a 64-byte block.');

        /** @var Words $words */
        $words = array_values($unpacked);
        assert($words !== [], description: 'BLAKE3 block words must not be empty.');

        return $words;
    }
}
