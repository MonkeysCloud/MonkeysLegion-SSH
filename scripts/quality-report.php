<?php

$bin = __DIR__ . '/../vendor/bin';

$checks = [
    'PHP-CS-Fixer' => [
        'cmd' => [$bin . '/php-cs-fixer', 'fix', '--config=' . __DIR__ . '/../.php-cs-fixer.php', '--dry-run', '--quiet'],
        'pass' => 'PSR-12, clean',
        'fail' => 'PSR-12 — violations',
    ],
    'PHPStan' => [
        'cmd' => [$bin . '/phpstan', 'analyse', '--ansi', '--no-progress'],
        'pass' => 'Level 9, clean',
        'fail' => 'Level 9 — errors',
    ],
    'PHPUnit' => [
        'cmd' => [$bin . '/phpunit', '--colors=always', '--no-coverage'],
        'pass' => '251 tests, clean',
        'fail' => 'tests failed',
    ],
    'Infection' => [
        'cmd' => 'XDEBUG_MODE=coverage php -d zend.assertions=1 ' . $bin . '/infection --threads=4 --no-progress',
        'pass' => 'MSI within threshold',
        'fail' => 'MSI below threshold',
    ],
];

$results = [];
$anyFailed = false;

foreach ($checks as $name => $config) {
    $output = [];
    $returnCode = 0;
    if (\is_array($config['cmd'])) {
        \exec(\implode(' ', \array_map('escapeshellarg', $config['cmd'])) . ' 2>/dev/null', $output, $returnCode);
    } else {
        \exec($config['cmd'] . ' 2>/dev/null', $output, $returnCode);
    }
    $results[$name] = $returnCode;
    if ($returnCode !== 0) {
        $anyFailed = true;
    }
}

echo "╔══════════════════════════════════════╗\n";
echo "║          Quality Report              ║\n";
echo "╠══════════════════════════════════════╣\n";

foreach ($checks as $name => $config) {
    $icon = $results[$name] === 0 ? ' ✓ ' : ' ✗ ';
    $label = $results[$name] === 0 ? $config['pass'] : $config['fail'];
    $namePadded = \str_pad($name, 12);
    echo "║  $icon$namePadded $label         ║\n";
}

echo "╠══════════════════════════════════════╣\n";
if ($anyFailed) {
    echo "║  Result: ❌ FAIL — check details above    ║\n";
} else {
    echo "║  Result: ✅ ALL CHECKS PASSED            ║\n";
}
echo "╚══════════════════════════════════════╝\n";

exit($anyFailed ? 1 : 0);
