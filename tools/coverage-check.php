<?php

declare(strict_types=1);

/**
 * Fail when Clover line coverage is below the requested percentage.
 *
 * Usage: php tools/coverage-check.php [build/coverage.xml] [100]
 */

/** @var list<string> $args */
$args = $_SERVER['argv'] ?? [];
$path = $args[1] ?? 'build/coverage.xml';
$minimum = (int) ($args[2] ?? '100');

if ($minimum < 0 || $minimum > 100) {
    fwrite(stream: STDERR, data: "coverage-check: minimum must be between 0 and 100\n");
    exit(2);
}

if (!is_file($path)) {
    fwrite(stream: STDERR, data: "coverage-check: file not found: {$path}\n");
    exit(2);
}

$xml = simplexml_load_file($path);

if ($xml === false) {
    fwrite(stream: STDERR, data: "coverage-check: invalid XML: {$path}\n");
    exit(2);
}

$project = $xml->project;

if ($project === null) {
    fwrite(stream: STDERR, data: "coverage-check: missing <project> in {$path}\n");
    exit(2);
}

$metrics = $project->metrics;

if ($metrics === null) {
    fwrite(stream: STDERR, data: "coverage-check: missing <metrics> in {$path}\n");
    exit(2);
}

$statementsAttr = $metrics['statements'] ?? null;
$coveredAttr = $metrics['coveredstatements'] ?? null;

if ($statementsAttr === null || $coveredAttr === null) {
    fwrite(stream: STDERR, data: "coverage-check: missing statement metrics in {$path}\n");
    exit(2);
}

$statements = (int) $statementsAttr;
$covered = (int) $coveredAttr;

if ($statements === 0) {
    fwrite(stream: STDERR, data: "coverage-check: no statements found in {$path}\n");
    exit(2);
}

$percent = ($covered / $statements) * 100;
printf("coverage: %d/%d statements (%.2f%%), required %d%%\n", $covered, $statements, $percent, $minimum);

if (($percent + 0.0001) < $minimum) {
    fwrite(stream: STDERR, data: "coverage-check: FAILED\n");
    exit(1);
}

echo "coverage-check: OK\n";
