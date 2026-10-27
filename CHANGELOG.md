# Changelog

All notable changes to `st-all-one/blake3` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the
project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-27

### Added

- Pure PHP 8.4 BLAKE3 implementation with 32-bit word masking and 64-bit counters.
- `StAllOne\Blake3\blake3_hash()` one-shot hash with arbitrary output length (XOF).
- `StAllOne\Blake3\blake3_keyed_hash()` keyed mode and
  `StAllOne\Blake3\blake3_derive_key()` key derivation.
- `StAllOne\Blake3\Hasher` incremental API (`new`, `newKeyed`, `newDeriveKey`, `update`,
  `finalize`).
- Official BLAKE3 test vectors (35 cases, three modes) and boundary/streaming tests.
- PHPStan max, Mago lint/format, PHPUnit strict, and 100% line coverage via PCOV.
- Reproducible development images for glibc (Debian trixie) and musl (Alpine 3.24),
  pinned by digest.
- Benchmark harness and documented baseline for future optimization.
- External consumer smoke test using a Composer path repository.
- Tag-driven GitHub Actions release workflow with pinned-image quality gates, source
  archives and `SHA256SUMS`.

### Changed

- Reworked the compression hot path to keep state and message words in local variables,
  inline the eight G mixes, rotate the message permutation in place, avoid a chunk
  substring copy, and pack the default 32-byte root directly.
  A/B benchmarks show ~2.2–3.2× over the initial array-based compressor with identical
  output on all official vectors.
- Benchmarks now disable assertions, matching production code paths.

### Security

- Keyed keys and derive-key material use `#[\SensitiveParameter]`, so exception traces
  and failure dumps do not expose key bytes.
- Added trace-redaction, XOF-at-offset, and keyed/derive streaming regression tests.

[0.1.0]: https://github.com/st-all-one/blake3-for-php/releases/tag/v0.1.0
