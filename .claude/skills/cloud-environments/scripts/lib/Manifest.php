<?php

declare(strict_types=1);

namespace CloudEnvironments;

use InvalidArgumentException;

/**
 * Reads a manifest and resolves what it declares for one environment kind.
 */
final class Manifest
{
    public const KINDS = ['preview', 'staging', 'production'];

    /**
     * @return array<string, mixed>
     */
    public static function load(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("manifest not found: {$path}");
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest)) {
            throw new InvalidArgumentException("manifest is not a JSON object: {$path}");
        }

        return $manifest;
    }

    /**
     * The variables for a kind: the defaults, with the kind's own values over them.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    public static function variablesFor(array $manifest, string $kind): array
    {
        $defaults = $manifest['variables'] ?? [];
        $own = $manifest['kinds'][$kind]['variables'] ?? [];

        return array_merge(is_array($defaults) ? $defaults : [], is_array($own) ? $own : []);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return mixed the kind's required map, as declared
     */
    public static function requiredFor(array $manifest, string $kind): mixed
    {
        return $manifest['kinds'][$kind]['required'] ?? [];
    }
}
