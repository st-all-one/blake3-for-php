#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

cat > "$tmp/composer.json" <<JSON
{
    "repositories": [
        {
            "type": "path",
            "url": "${root}",
            "options": {
                "symlink": true,
                "versions": {
                    "st-all-one/blake3": "0.1.0"
                }
            }
        }
    ],
    "require": {
        "st-all-one/blake3": "^0.1"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
JSON

cd "$tmp"
composer install --no-interaction --no-progress --no-dev --no-scripts >/dev/null

php -r '
require "vendor/autoload.php";

use function StAllOne\Blake3\blake3_derive_key;
use function StAllOne\Blake3\blake3_hash;
use function StAllOne\Blake3\blake3_keyed_hash;

$key = "whats the Elvish word for friend";
$context = "BLAKE3 2019-12-27 16:29:52 test vectors context";

$checks = [
    blake3_hash("") === "af1349b9f5f9a1a6a0404dea36dcc9499bcb25c9adc112b7cc9a93cae41f3262",
    blake3_keyed_hash($key, "") === "92b2b75604ed3c761f9d6f62392c8a9227ad0ea3f09573e783f1498a4ed60d26",
    blake3_derive_key($context, "") === "2cc39783c223154fea8dfb7c1b1660f2ac2dcbd1c1de8277b0b0dd39b7e50d7d",
    blake3_hash("abc", 32, true) !== "",
];

foreach ($checks as $ok) {
    if (!$ok) {
        fwrite(STDERR, "consumer-smoke: mismatch\n");
        exit(1);
    }
}

echo "consumer-smoke: OK\n";
'
