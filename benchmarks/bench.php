<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use StAllOne\Blake3\Hasher;

use function StAllOne\Blake3\blake3_derive_key;
use function StAllOne\Blake3\blake3_hash;
use function StAllOne\Blake3\blake3_keyed_hash;

const MIB = 1_048_576;

const ROUNDS = 3;

$key = str_repeat(string: "\x42", times: 32);
$context = 'blake3-php benchmark v1';

/**
 * @var list<array{name: string, size: int, iterations: int, run: Closure(string): string}> $cases
 */
$cases = [
    [
        'name' => '64 B hash',
        'size' => 64,
        'iterations' => 5_000,
        'run' => static fn(string $data): string => blake3_hash($data),
    ],
    [
        'name' => '1 KiB hash',
        'size' => 1024,
        'iterations' => 1_000,
        'run' => static fn(string $data): string => blake3_hash($data),
    ],
    [
        'name' => '64 KiB hash',
        'size' => 64 * 1024,
        'iterations' => 20,
        'run' => static fn(string $data): string => blake3_hash($data),
    ],
    [
        'name' => '1 MiB hash',
        'size' => MIB,
        'iterations' => 3,
        'run' => static fn(string $data): string => blake3_hash($data),
    ],
    [
        'name' => '64 B keyed',
        'size' => 64,
        'iterations' => 5_000,
        'run' => static fn(string $data): string => blake3_keyed_hash(key: $key, data: $data),
    ],
    [
        'name' => '1 KiB keyed',
        'size' => 1024,
        'iterations' => 1_000,
        'run' => static fn(string $data): string => blake3_keyed_hash(key: $key, data: $data),
    ],
    [
        'name' => '64 KiB keyed',
        'size' => 64 * 1024,
        'iterations' => 20,
        'run' => static fn(string $data): string => blake3_keyed_hash(key: $key, data: $data),
    ],
    [
        'name' => '1 MiB keyed',
        'size' => MIB,
        'iterations' => 3,
        'run' => static fn(string $data): string => blake3_keyed_hash(key: $key, data: $data),
    ],
    [
        'name' => '64 B derive',
        'size' => 64,
        'iterations' => 5_000,
        'run' => static fn(string $data): string => blake3_derive_key(context: $context, keyMaterial: $data),
    ],
    [
        'name' => '1 KiB derive',
        'size' => 1024,
        'iterations' => 1_000,
        'run' => static fn(string $data): string => blake3_derive_key(context: $context, keyMaterial: $data),
    ],
    [
        'name' => '64 KiB derive',
        'size' => 64 * 1024,
        'iterations' => 20,
        'run' => static fn(string $data): string => blake3_derive_key(context: $context, keyMaterial: $data),
    ],
    [
        'name' => '1 MiB derive',
        'size' => MIB,
        'iterations' => 3,
        'run' => static fn(string $data): string => blake3_derive_key(context: $context, keyMaterial: $data),
    ],
    [
        'name' => '1 MiB stream 4 KiB',
        'size' => MIB,
        'iterations' => 3,
        'run' => static function (string $data): string {
            $hasher = Hasher::new();
            $length = strlen($data);

            for ($offset = 0; $offset < $length; $offset += 4096) {
                $hasher->update(substr(string: $data, offset: $offset, length: 4096));
            }

            return $hasher->finalize();
        },
    ],
    [
        'name' => '1 KiB hash -> 1 KiB XOF',
        'size' => 1024,
        'iterations' => 1_000,
        'run' => static fn(string $data): string => blake3_hash(data: $data, length: 1024),
    ],
];

$results = [];

foreach ($cases as $case) {
    $data = str_repeat("\0", $case['size']);
    $case['run']($data);

    $times = [];

    for ($round = 0; $round < ROUNDS; $round++) {
        $start = hrtime(as_number: true);

        for ($iteration = 0; $iteration < $case['iterations']; $iteration++) {
            $case['run']($data);
        }

        $elapsed = hrtime(as_number: true) - $start;
        $times[] = ($elapsed / $case['iterations']) / 1_000_000_000;
    }

    sort($times);

    $seconds = $times[intdiv(num1: count($times), num2: 2)];
    $results[] = [
        'name' => $case['name'],
        'size' => $case['size'],
        'iterations' => $case['iterations'],
        'seconds' => $seconds,
        'mib_per_second' => ($case['size'] / MIB) / $seconds,
        'nanoseconds_per_byte' => ($seconds * 1_000_000_000) / $case['size'],
    ];
}

$pcovSetting = ini_get('pcov.enabled');
$opcacheSetting = ini_get('opcache.enable_cli');
$assertionsSetting = ini_get('zend.assertions');
$cpu = 'unknown';

if (is_readable('/proc/cpuinfo')) {
    $cpuInfo = file_get_contents('/proc/cpuinfo');

    if (is_string($cpuInfo) && preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $matches) === 1) {
        $cpu = trim($matches[1]);
    }
}

$pcov = 'absent';
if (extension_loaded('pcov')) {
    $pcov = is_string($pcovSetting) ? $pcovSetting : 'loaded';
}

$opcacheCli = is_string($opcacheSetting) ? $opcacheSetting : 'unknown';
$assertions = is_string($assertionsSetting) ? $assertionsSetting : 'unknown';
$environment = [
    'php' => PHP_VERSION,
    'arch' => php_uname('m'),
    'os' => PHP_OS_FAMILY,
    'cpu' => $cpu,
    'pcov' => $pcov,
    'opcache_cli' => $opcacheCli,
    'assertions' => $assertions,
    'rounds' => ROUNDS,
];

/** @var list<string> $argv */
$argv = $_SERVER['argv'] ?? [];
$json = in_array(needle: '--json', haystack: $argv, strict: true);

if ($json) {
    echo json_encode(value: [
        'environment' => $environment,
        'results' => $results,
    ], flags: JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    echo "\n";
    exit(0);
}

printf(
    "PHP %s (%s, %s), PCOV=%s, OPcache CLI=%s, assertions=%s, rounds=%d\n",
    $environment['php'],
    $environment['arch'],
    $environment['os'],
    $environment['pcov'],
    $environment['opcache_cli'],
    $environment['assertions'],
    ROUNDS,
);
printf("CPU: %s\n", $environment['cpu']);
printf("%-26s %12s %10s %12s\n", 'case', 'median ms', 'MiB/s', 'ns/byte');
printf("%s\n", str_repeat(string: '-', times: 64));

foreach ($results as $result) {
    printf(
        "%-26s %12.3f %10.3f %12.2f\n",
        $result['name'],
        $result['seconds'] * 1000,
        $result['mib_per_second'],
        $result['nanoseconds_per_byte'],
    );
}
