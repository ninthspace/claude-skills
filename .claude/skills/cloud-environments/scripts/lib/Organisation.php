<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * The authenticated organisation, read from `application:list` (each application carries it),
 * and the manifest's own application in it.
 */
final class Organisation
{
    /**
     * @param  list<array<string, mixed>>  $apps
     * @param  array<string, mixed>  $manifest
     */
    public static function check(array $apps, array $manifest): void
    {
        if ($apps === []) {
            throw new PlanRefused('cannot confirm the organisation: application:list returned no applications');
        }

        $org = $apps[0]['organization'] ?? [];
        $expected = strtolower(trim($manifest['organisation']));

        if ($expected !== strtolower(trim((string) ($org['name'] ?? ''))) && $expected !== strtolower(trim((string) ($org['slug'] ?? '')))) {
            throw new PlanRefused('the authenticated organisation is '.($org['name'] ?? 'unknown').", but the manifest is for {$manifest['organisation']}");
        }
    }

    /**
     * @param  list<array<string, mixed>>  $apps
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>|null
     */
    public static function ownedApplication(array $apps, array $manifest): ?array
    {
        foreach ($apps as $app) {
            if (($app['name'] ?? null) === $manifest['application']) {
                return $app;
            }
        }

        return null;
    }

    /**
     * A managed queue among an environment's instances: Cloud types it `managed_queue`.
     *
     * @param  array<string, mixed>  $instance
     */
    public static function isQueue(array $instance): bool
    {
        return ($instance['queueStatus'] ?? null) !== null
            || ($instance['visibilityTimeout'] ?? null) !== null
            || in_array($instance['type'] ?? null, ['queue', 'managed_queue', 'managed-queue'], true);
    }

    /**
     * The kind an environment name belongs to: `preview-*`, `staging` or `production`.
     */
    public static function kindOf(string $environment): ?string
    {
        if (str_starts_with($environment, 'preview-')) {
            return 'preview';
        }

        return in_array($environment, ['staging', 'production'], true) ? $environment : null;
    }
}
