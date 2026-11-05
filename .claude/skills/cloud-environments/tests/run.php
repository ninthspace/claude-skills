<?php

declare(strict_types=1);

// Usage: php tests/run.php [test-name-substring]
// Refuses to start unless CLOUD_BIN is the stub, so no test can reach a real Cloud account.

$stub = realpath(__DIR__.'/stub/cloud');
$bin = getenv('CLOUD_BIN');
$bin = ($bin === false || $bin === '') ? $stub : $bin;
$resolved = realpath($bin) ?: (($found = trim((string) shell_exec('command -v '.escapeshellarg($bin)))) !== '' ? realpath($found) : false);

if ($resolved !== $stub) {
    fwrite(STDERR, "refusing to run: CLOUD_BIN is {$bin}, not the stub {$stub}\n");
    exit(2);
}

putenv("CLOUD_BIN={$stub}");

// A PHP warning or notice in code under test fails the test.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__.'/../scripts/lib/autoload.php';
require __DIR__.'/harness.php';
require __DIR__.'/state.php';

foreach (glob(__DIR__.'/*Test.php') as $file) {
    require $file;
}

$filter = $argv[1] ?? '';
$passed = 0;
$failed = 0;
$skipped = 0;

foreach ($GLOBALS['__tests'] as $name => $fn) {
    if ($filter !== '' && ! str_contains($name, $filter)) {
        continue;
    }

    try {
        $fn();
        echo "ok   {$name}\n";
        $passed++;
    } catch (SkipTest $e) {
        echo "skip {$name}: {$e->getMessage()}\n";
        $skipped++;
    } catch (Throwable $e) {
        echo "FAIL {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n{$passed} passed, {$failed} failed, {$skipped} skipped\n";

if ($passed + $failed + $skipped === 0) {
    fwrite(STDERR, "no test matched '{$filter}'\n");
    exit(3);
}

exit($failed > 0 ? 1 : 0);
