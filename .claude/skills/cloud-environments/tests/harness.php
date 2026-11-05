<?php

declare(strict_types=1);

final class AssertionFailure extends Exception {}

final class SkipTest extends Exception {}

/** @var array<string, callable> $GLOBALS['__tests'] */
$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][$name] = $fn;
}

function fail(string $message): never
{
    throw new AssertionFailure($message);
}

function assertTrue(mixed $value, string $message = 'expected true'): void
{
    if ($value !== true) {
        fail($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        fail(trim($message.' expected '.json_encode($expected).', got '.json_encode($actual)));
    }
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    if (! str_contains($haystack, $needle)) {
        fail(trim($message.' expected to find '.json_encode($needle).' in '.json_encode($haystack)));
    }
}

function assertNotContains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        fail(trim($message.' did not expect '.json_encode($needle).' in '.json_encode($haystack)));
    }
}

/**
 * @param  list<string>  $errors
 */
function assertErrorNames(string $field, array $errors, string $message = ''): void
{
    foreach ($errors as $error) {
        if (str_starts_with($error, $field.':') || str_starts_with($error, $field.'.')) {
            return;
        }
    }

    fail(trim($message.' expected an error naming '.$field.', got '.json_encode($errors)));
}

/**
 * @param  list<string>  $args
 * @param  array<string, string>  $env
 * @return array{exit: int, out: string, err: string}
 */
function runProcess(array $args, array $env = [], ?string $cwd = null): array
{
    $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, array_merge(getenv(), $env));
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    $exit = proc_close($process);

    return ['exit' => $exit, 'out' => $out, 'err' => $err];
}

function skillDir(): string
{
    return dirname(__DIR__);
}

function tempDir(): string
{
    $dir = sys_get_temp_dir().'/cloud-env-test-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

/**
 * @return array<string, mixed>
 */
function validManifest(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/fixtures/manifest-valid.json'), true, 512, JSON_THROW_ON_ERROR);
}
