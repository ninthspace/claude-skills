<?php

declare(strict_types=1);

namespace CloudEnvironments;

use RuntimeException;

/**
 * A plan on disk and its hash. The hash is SHA-256 of the canonical plan (keys sorted
 * recursively, fixed encoding flags, no timestamps or absolute paths), over everything
 * except the stored hash itself.
 */
final class PlanFile
{
    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * @param  array<string, mixed>  $plan
     */
    public static function hash(array $plan): string
    {
        unset($plan['hash']);

        return hash('sha256', json_encode(self::canonical($plan), self::FLAGS));
    }

    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * Writes the plan with its hash to `.cloud/plans/{environment}.json` beside the
     * manifest, and refuses when that directory is not ignored by git.
     *
     * @param  array<string, mixed>  $plan
     */
    public static function write(string $root, array $plan): string
    {
        $dir = $root.'/.cloud/plans';
        $probe = '.cloud/plans/'.$plan['environment'].'.json';
        $check = proc_open(['git', 'check-ignore', '-q', $probe], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        $ignored = is_resource($check) && proc_close($check) === 0;

        if (! $ignored) {
            throw new RuntimeException("refusing to write a plan: {$dir} is not git-ignored (add .cloud/plans/ to .gitignore)");
        }

        if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
            throw new RuntimeException("could not create {$dir}");
        }

        $plan['hash'] = self::hash($plan);
        $path = "{$dir}/{$plan['environment']}.json";
        file_put_contents($path, json_encode($plan, self::FLAGS | JSON_PRETTY_PRINT)."\n");

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        $plan = json_decode((string) @file_get_contents($path), true);

        if (! is_array($plan)) {
            throw new RuntimeException("not a plan file: {$path}");
        }

        foreach (['version' => 'is_int', 'kind' => 'is_string', 'environment' => 'is_string', 'branch' => 'is_string', 'application' => 'is_string', 'steps' => 'is_array', 'state' => 'is_array'] as $field => $check) {
            if (! isset($plan[$field]) || ! $check($plan[$field])) {
                throw new RuntimeException("not a plan file: {$path} has no {$field}");
            }
        }

        return $plan;
    }
}
