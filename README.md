# BLAKE3 for PHP

Pure PHP 8.4 implementation of [BLAKE3](https://github.com/BLAKE3-team/BLAKE3): fast,
deterministic, framework-agnostic, and dependency-free at runtime.

- `blake3_hash(string $data, int $length = 32, bool $rawOutput = false): string`
- keyed hashing and key derivation
- arbitrary-length output (XOF)
- incremental hashing with `Hasher::update()` / `Hasher::finalize()`
- no extensions required; `ext-blake3` is optional and never required
- verified against the official BLAKE3 test vectors on glibc and musl

## Requirements

- PHP 8.4 or newer
- 64-bit PHP (`php-64bit`)
- no framework, no service container, no PSR dependency

## Installation

```bash
composer require st-all-one/blake3
```

The package is a library and exposes:

| Symbol | Purpose |
|---|---|
| `StAllOne\Blake3\Hasher` | incremental hasher and static factories |
| `StAllOne\Blake3\blake3_hash()` | one-shot hash |
| `StAllOne\Blake3\blake3_keyed_hash()` | keyed hash (MAC/PRF) |
| `StAllOne\Blake3\blake3_derive_key()` | key derivation from a context |

Import the function explicitly; there is **no global `blake3_hash`** to avoid a fatal
redeclaration when `ext-blake3` is loaded:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use function StAllOne\Blake3\blake3_hash;

echo blake3_hash('hello'), PHP_EOL;            // 64 lowercase hex chars
echo blake3_hash('hello', 64), PHP_EOL;        // XOF: 128 hex chars
echo bin2hex(blake3_hash('hello', 32, true));  // raw 32-byte output
```

## API

### `blake3_hash(string $data, int $length = 32, bool $rawOutput = false): string`

Hashes `$data`. `$length` is the number of output bytes and must be at least 1.
Returns lowercase hexadecimal by default, or raw bytes when `$rawOutput` is `true`.

### `blake3_keyed_hash(string $key, string $data, int $length = 32, bool $rawOutput = false): string`

Keyed BLAKE3. `$key` must be exactly 32 bytes. Use it for MAC/PRF-style constructions
where both sides share a secret key.

### `blake3_derive_key(string $context, string $keyMaterial, int $length = 32, bool $rawOutput = false): string`

Derives key material from a globally unique, application-specific `$context` and the
input `$keyMaterial`. Different contexts derive independent keys.

### `Hasher`

```php
use StAllOne\Blake3\Hasher;

$hasher = Hasher::new();
$hasher->update('first chunk');
$hasher->update('second chunk');
echo $hasher->finalize();       // hex
echo $hasher->finalize(64);     // XOF, hex
echo $hasher->finalize(32, true); // raw bytes
```

`finalize()` does not mutate the hasher. It can be called repeatedly, and `update()`
may continue afterwards; the result is the hash of all bytes fed so far.

```php
$hasher = Hasher::newKeyed(str_repeat("\x42", 32));
$hasher = Hasher::newDeriveKey('my-app/v1/session-key');
```

Invalid key length throws `InvalidArgumentException`; an output length below 1 throws
`ValueError`.

## Security notes

- BLAKE3 is a general-purpose cryptographic hash, **not a password hash**. Use
  `password_hash()` with Argon2id for passwords.
- Keyed hashing is a PRF/MAC-style construction; keep the 32-byte key secret.
- Derivation contexts must be globally unique and application-specific. Changing the
  context changes the derived key.
- Compare hashes with `hash_equals()` when the comparison is security-sensitive.
- Keyed keys and derive-key material are marked `#[\SensitiveParameter]`; exception
  traces redact them instead of exposing key bytes.
- This is a pure PHP implementation. For very high-throughput hot paths, measure the
  native `ext-blake3` extension and prefer it when available.

## Testing and quality gates

Everything runs inside the two pinned images:

| Base | Image |
|---|---|
| glibc (Debian trixie) | `php:8.4.27-fpm-trixie@sha256:af172814…ff149c2` |
| musl (Alpine 3.24) | `php:8.4.27-fpm-alpine3.24@sha256:93aa3f61…5076c00d` |

```bash
make install     # Composer dependencies (lock file)
make check       # composer validate + Mago + PHPStan max + PHPUnit on both bases
make coverage    # PCOV line coverage, fails below 100%
make smoke       # installs the package in a temporary external project
make bench       # performance baseline on glibc and musl
```

The test suite covers all 35 official vectors in the three modes (hash, keyed, derive)
at 131-byte XOF, plus chunk/block boundaries, streaming splits, XOF prefixes, error
paths, and state reuse.

## Releasing

Releases are tag-driven and use `.github/workflows/release.yml`.

1. Commit and merge the release changes to `main`, then confirm `make check` is green.
2. Update `CHANGELOG.md` so the first version heading is exactly `## [X.Y.Z] - YYYY-MM-DD`.
3. Create and push an **annotated** tag:

```bash
git tag -a vX.Y.Z -m "BLAKE3 for PHP X.Y.Z"
git push origin vX.Y.Z
```

The workflow rejects lightweight tags, tags not reachable from `main`, and tags whose
version does not match the first changelog entry. It then runs `make check`, `make smoke`
and `make audit` on both pinned images, creates `.tar.gz`/`.zip` source archives plus
`SHA256SUMS`, and publishes a GitHub Release. A version containing `-` is marked as a
prerelease. Packagist picks up the tag when the repository integration is configured.

Never move or delete a published release tag; publish a new patch version instead.

## Performance

This is scalar PHP; there is no SIMD. The benchmark harness measures one-shot hash,
keyed hash, derive-key, streaming, and XOF on both libc variants:

```bash
make bench
make bench-json
```

Baseline on the pinned images (PHP 8.4.27, x86_64, PCOV/assertions off, OPcache CLI off):
**1 MiB hash ≈ 102–113 ms (≈ 8.9–9.8 MiB/s)**; 1 KiB hash ≈ 9.4–10.2 MiB/s. The optimized
compressor is ~2.2–3.2× faster than the initial array-based implementation across
small, large and sequential scenarios; see the A/B table in `BENCHMARKS.md`. glibc and
musl are within measurement noise for this workload.

Results are recorded in [`BENCHMARKS.md`](BENCHMARKS.md). Treat them as a baseline for
optimization on the same machine, not as a universal throughput claim. PCOV and assertions
are disabled for benchmark runs (`-d pcov.enabled=0 -d zend.assertions=-1`); OPcache CLI
and JIT are off unless explicitly enabled.

For production, keep OPcache enabled and preload `vendor/autoload.php`, `src/Hasher.php`
and `src/functions.php`. Do not enable JIT for this workload without measuring: CLI
tracing was slower than the interpreter here, and the result is deployment-specific.

## Design constraints

- one cohesive `Hasher` class plus three functions — no interfaces, traits, factories,
  or framework adapters;
- no runtime dependencies;
- 32-bit words stored in 64-bit PHP integers with explicit masking;
- chunk state and CV stack mirror the reference implementation in `REF/BLAKE3`;
- official test vectors are versioned and never edited.

See [`PLAN.md`](PLAN.md) for the implementation sequence and [`AGENTS.md`](AGENTS.md)
for project rules.

## License

Apache-2.0. The BLAKE3 test vectors are from the official BLAKE3 repository and retain
their original licensing; see [`tests/fixtures/README.md`](tests/fixtures/README.md).
