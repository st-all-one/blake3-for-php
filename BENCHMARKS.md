# Performance baseline

This is the **reference baseline for future optimization**, not a cross-machine
throughput claim. BLAKE3 is implemented in scalar PHP; there is no SIMD or FFI path.

## Method

- Harness: `benchmarks/bench.php`.
- Command: `make bench` (runs both pinned images) or
  `php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php`.
- Each case runs one warm-up call, then three timed rounds. Within a round the case's
  `iterations` are executed sequentially (for example 5,000 × 64 B, 1,000 × 1 KiB,
  20 × 64 KiB, 3 × 1 MiB); the table reports the median **per call**.
- Input is a zero-filled string of the stated size. Compression cost is data-independent.
- PCOV is disabled, assertions are disabled (`zend.assertions=-1`), OPcache CLI is off
  (PHP CLI default) and JIT is disabled. Each row counts **input** bytes; the XOF row
  produces 1 KiB of output and is therefore not directly comparable.

Environment: Fedora Linux 44 (Workstation), kernel 7.2.11-200.fc44.x86_64, AMD Ryzen 7
7735HS, Docker 29.9.2. The full sanitized inventory is in "Test environment" below.
Run-to-run variation is a few percent; the A/B section below shows observed ranges.

## Test environment

Collected with `inxi 3.3.41`, `lscpu`, `docker version`, `docker info`, and the pinned
images on 2026-10-27T09:42:51-03:00. Host identifiers (hostname, serials, UUIDs, MAC/IP
addresses, disk and battery identifiers, bus IDs, firmware serial, and exact network
interface names) are deliberately omitted. The host was shared: other Docker containers
were running during collection, and the benchmark is not CPU-pinned.

### Host, CPU and memory

| Item | Value |
|---|---|
| Host OS | Fedora Linux 44 (Workstation Edition) |
| Kernel | `7.2.11-200.fc44.x86_64` |
| Architecture | x86_64, 64-bit, little-endian |
| Machine | Lenovo IdeaPad Slim 3 15ARP10 laptop, bare metal (`systemd-detect-virt` = none), AMD-V |
| CPU | AMD Ryzen 7 7735HS with Radeon Graphics, Zen 3+, family 25/model 68/stepping 1 |
| Topology | 8 cores, 16 threads, 1 socket; 16 logical CPUs visible to Docker |
| Cache | L1d 256 KiB + L1i 256 KiB, L2 4 MiB, L3 16 MiB |
| CPU frequency | governor `performance`, driver `amd-pstate-epp`, boost enabled; observed ~4.06 GHz (range 0.407–4.831 GHz) |
| Host memory | ~14 GiB RAM visible, 8 GiB swap |
| Load during collection | shared host with 52 Docker containers; ~9.9 GiB RAM in use |
| Storage | btrfs (also the Docker storage driver) |

### Container runtime

| Item | Value |
|---|---|
| Docker | client/server 29.9.2, API 1.56 |
| Server platform | Linux/amd64 |
| Cgroup | v2, `systemd` driver |
| Docker resources | 16 CPUs, 15,362,813,952 B (≈14.3 GiB) visible |
| Limits | no `--cpus`, `--cpuset-cpus`, or `--memory` in `DOCKER_RUN`; default container limits |
| CPU pinning | none |
| Benchmark command | `make bench` → `php -d pcov.enabled=0 -d zend.assertions=-1 benchmarks/bench.php` |

### Images and toolchain

| Item | Value |
|---|---|
| glibc image | `php:8.4.27-fpm-trixie@sha256:af1728147713459bda545446e3fae81cea5119543c46f28b9a077c7b3ff149c2` |
| glibc guest | Debian GNU/Linux 13 (trixie) |
| musl image | `php:8.4.27-fpm-alpine3.24@sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d` |
| musl guest | Alpine Linux 3.24.3 |
| PHP | 8.4.27 CLI, NTS (built 2026-10-21), in both images |
| Composer | 2.10.3 (2026-08-27) |
| PCOV | 1.0.12, installed but disabled for benchmark runs |
| OPcache | 8.4.27; `opcache.enable_cli=Off`, `opcache.jit=disable`, `opcache.jit_buffer_size=64M` |
| PHP runtime | `memory_limit=512M`, `date.timezone=UTC`; image default `zend.assertions=1`, overridden to `-1` by the benchmark command |
| Quality toolchain | PHPUnit 13.4.5, PHPStan 2.3.2, Mago 1.59.0 (not used during timing) |

### Reproduction notes

1. Use the same pinned images (`make build` or `make install`) and the same host class.
2. Prefer the `performance` CPU governor and an idle host; this run shared the host with
   other containers, so absolute numbers vary by a few percent.
3. Run `make bench` on both images. The harness warms up once, executes three timed rounds,
   and reports the median per call; each round runs the case's sequential `iterations`.
4. Compare only same-host/current-run medians. The A/B baseline was a frozen copy of the
   pre-optimization `Hasher`, loaded in the same PHP process with the same flags.
5. Do not treat these numbers as a cross-machine throughput claim.

## A/B: pre-optimization vs current

The baseline was the original implementation with an array state, `round()`/`mix()`/
`rotateRight()` calls, a `permute()` allocation per round, and per-block string buffering.
Both implementations were loaded in the same PHP process, on the same host, with
`pcov.enabled=0`, `zend.assertions=-1` and `opcache.enable_cli=0`. The table combines two
independent runs (9 medians for small/large, 3 for sequential/streaming) to show the
observed range.

| Scenario | Before | After | Speedup |
|---|---:|---:|---:|
| 64 B hash | 0.020–0.029 ms | 0.008–0.010 ms | **2.5–2.8×** |
| 1 KiB hash | 0.293–0.311 ms | 0.096–0.132 ms | **2.2–3.2×** |
| 64 KiB hash | 19.19–19.24 ms | 6.56–6.76 ms | **2.84–2.94×** |
| 1 MiB hash | 307.2–307.4 ms | 97.5–112.2 ms | **2.74–3.15×** |
| 1,000 × 64 B | 17.91–18.18 ms | 7.11–7.19 ms | **2.49–2.56×** |
| 1,000 × 1 KiB | 282.1–284.6 ms | 102.5–105.2 ms | **2.68–2.78×** |
| 100 × 64 KiB | 1,932–1,936 ms | 667–672 ms | **2.88–2.90×** |
| 1 MiB streamed in 4 KiB updates | 312.5–315.4 ms | 101.4–113.7 ms | **2.77–3.08×** |

Worst observed speedup was **2.2×**; large inputs are consistently **2.7–3.2×** faster.
The current compressor holds the 16 state words and 16 message words in local variables,
inlines the eight G mixes per round, and rotates the message permutation in place. It also
avoids a chunk substring copy, computes full-chunk chaining values without an intermediate
output array, and packs the default 32-byte root directly.

Correctness was checked against the frozen baseline over **2,361 cases**: the 35 official
vectors in hash/keyed/derive at 131-byte XOF; one-shot lengths 0..2100 plus 4 KiB, 8 KiB,
31,744 B, 102,400 B, 1 MiB and 1 MiB+1; the streaming chunk matrix; and XOF lengths
1..4096. The permanent suite separately checks **354 tests / 693 assertions** and 100% of
statements on both libc variants.

Measured and rejected: a fully unrolled seven-round body (no consistent end-to-end gain,
+25 KB of code), direct uncompressed-block processing (~1.00–1.03×, extra boundary
complexity), 1-based `unpack` indexing (~1.00–1.03×), returning only eight words from
`compress` (~1.01×), a literal mask instead of the class constant (~1.01×), and hoisting
the XOF output fields plus explicit 16-argument packing (~0.91–1.00× on long XOF; reverted).
JIT `tracing` was also measured without PCOV: 1 MiB was 111 ms vs 105 ms without JIT, and
the 1 KiB case was slower; JIT is not assumed.

## glibc — `php:8.4.27-fpm-trixie`

| Case | Median | MiB/s | ns/byte |
|---|---:|---:|---:|
| 64 B hash | 0.006 ms | 9.657 | 98.76 |
| 1 KiB hash | 0.096 ms | 10.188 | 93.61 |
| 64 KiB hash | 6.476 ms | 9.650 | 98.82 |
| 1 MiB hash | 101.988 ms | 9.805 | 97.26 |
| 64 B keyed | 0.007 ms | 8.289 | 115.05 |
| 1 KiB keyed | 0.101 ms | 9.669 | 98.63 |
| 64 KiB keyed | 6.773 ms | 9.228 | 103.34 |
| 1 MiB keyed | 105.154 ms | 9.510 | 100.28 |
| 64 B derive | 0.014 ms | 4.316 | 220.96 |
| 1 KiB derive | 0.106 ms | 9.207 | 103.59 |
| 64 KiB derive | 6.654 ms | 9.393 | 101.53 |
| 1 MiB derive | 110.980 ms | 9.011 | 105.84 |
| 1 MiB stream, 4 KiB chunks | 116.273 ms | 8.600 | 110.89 |
| 1 KiB hash → 1 KiB XOF | 0.201 ms | 4.850 | 196.64 |

## musl — `php:8.4.27-fpm-alpine3.24`

| Case | Median | MiB/s | ns/byte |
|---|---:|---:|---:|
| 64 B hash | 0.007 ms | 9.153 | 104.19 |
| 1 KiB hash | 0.104 ms | 9.422 | 101.22 |
| 64 KiB hash | 7.158 ms | 8.732 | 109.22 |
| 1 MiB hash | 112.939 ms | 8.854 | 107.71 |
| 64 B keyed | 0.008 ms | 8.081 | 118.01 |
| 1 KiB keyed | 0.106 ms | 9.183 | 103.85 |
| 64 KiB keyed | 7.198 ms | 8.683 | 109.83 |
| 1 MiB keyed | 100.148 ms | 9.985 | 95.51 |
| 64 B derive | 0.015 ms | 4.165 | 228.96 |
| 1 KiB derive | 0.111 ms | 8.778 | 108.65 |
| 64 KiB derive | 6.671 ms | 9.369 | 101.79 |
| 1 MiB derive | 115.416 ms | 8.664 | 110.07 |
| 1 MiB stream, 4 KiB chunks | 106.481 ms | 9.391 | 101.55 |
| 1 KiB hash → 1 KiB XOF | 0.172 ms | 5.685 | 167.74 |

## Observations

- The optimized compressor sustains roughly **8.6–10.2 MiB/s** for multi-KiB inputs,
  versus ~3.2 MiB/s before. glibc and musl are within measurement noise; algorithmic cost
  dominates the C library.
- Small derive-key cases include the fixed cost of hashing the context string; the
  per-byte throughput is therefore lower at 64 B.
- Streaming with 4 KiB chunks is effectively identical to one-shot: the tree/state
  machine, not string handling, dominates.
- The XOF row is deliberately included to make future root-output optimizations visible;
  its MiB/s number is based on input bytes only.

## Optimization candidates (not implemented)

1. Reduce XOF packing/substring overhead for non-32/non-64 output lengths without
   regressing the measured 32-byte and multi-block cases; the first attempt was reverted.
2. Re-evaluate JIT in a production SAPI without PCOV. CLI `tracing` was slower here, so
   this needs evidence per deployment, not a blanket recommendation.
3. Batch APIs are out of scope unless a caller can show repeated setup costs dominating;
   the 1,000 × 64 B row already amortizes the hot path.

Every change must keep the 35 official vectors and the 2,361-case A/B regression set green
on both images, and must be compared with `make bench` on the same host before/after.
