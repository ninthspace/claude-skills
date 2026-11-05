<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Runs the binary named by CLOUD_BIN (default `cloud`) with `--json --no-interaction` and
 * decodes what it prints. A runner made without `writes` refuses every command the catalog
 * marks as writing, so a read-only run cannot change Cloud whatever calls it makes.
 */
final class Cloud
{
    public function __construct(private readonly string $bin, private readonly bool $writes = false) {}

    public static function fromEnvironment(bool $writes = false): self
    {
        $bin = getenv('CLOUD_BIN');

        return new self(($bin === false || $bin === '') ? 'cloud' : $bin, $writes);
    }

    /**
     * @param  list<string>  $args  command first, then its arguments and options
     */
    public function json(array $args, bool $showSensitive = false, bool $lastDocument = false, ?string $stdin = null): mixed
    {
        $command = $args[0];
        $rest = array_slice($args, 1);

        Catalog::assertEmittable($command, $rest);

        if (Catalog::isWrite($command) && ! $this->writes) {
            throw new CloudError("refusing to run {$command}: this run is read-only");
        }

        $argv = array_merge([$this->bin, $command], $rest, ['--json', '--no-interaction'], $showSensitive ? ['--show-sensitive'] : []);
        // stderr goes to a file, so a child that writes a lot to it cannot block while stdout is read.
        $errFile = tempnam(sys_get_temp_dir(), 'cloud-env-err-');
        // Without a value to pipe, the child reads nothing, so a command that waits on stdin cannot hang.
        $descriptors = [0 => $stdin !== null ? ['pipe', 'r'] : ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']];

        $process = proc_open($argv, $descriptors, $pipes);

        if (! is_resource($process)) {
            @unlink($errFile);

            throw new CloudError("could not start {$this->bin}");
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
        }

        $out = (string) stream_get_contents($pipes[1]);
        $exit = proc_close($process);
        $err = (string) file_get_contents($errFile);
        @unlink($errFile);

        // A sensitive read, or a call given a value on stdin, never repeats the CLI's output.
        $quiet = $showSensitive || $stdin !== null;

        if ($exit !== 0) {
            throw new CloudError("{$command} failed (exit {$exit})".($quiet ? '' : ': '.trim($err !== '' ? $err : $out)));
        }

        $decoded = json_decode($out, true);

        if ($decoded === null && $lastDocument) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $out)), fn ($line) => $line !== ''));
            $decoded = $lines === [] ? null : json_decode(end($lines), true);
        }

        if ($decoded === null && trim($out) !== 'null') {
            throw new CloudError("{$command} returned output that is not JSON".($quiet ? '' : ': '.substr(trim($out), 0, 200)));
        }

        return $decoded;
    }

    /**
     * A read that must return a list. An object (an error body) or anything else is an
     * error, never an empty list.
     *
     * @param  list<string>  $args
     * @return list<array<string, mixed>>
     */
    public function list(array $args): array
    {
        $decoded = $this->json($args);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new CloudError("{$args[0]} did not return a list: ".substr(json_encode($decoded) ?: '', 0, 200));
        }

        return $decoded;
    }

    /**
     * A command that prints a JSON status line per change before its result (`command:run`)
     * is read by its last line.
     *
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    public function lastObject(array $args): array
    {
        $decoded = $this->json($args, false, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new CloudError("{$args[0]} did not end with an object");
        }

        return $decoded;
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    public function object(array $args, bool $showSensitive = false): array
    {
        $decoded = $this->json($args, $showSensitive);

        if (! is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new CloudError("{$args[0]} did not return an object");
        }

        return $decoded;
    }
}
