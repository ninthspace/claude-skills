<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Deletes one environment of the manifest's application, and its schema when no other
 * environment uses it. Needs `--confirm` with the environment's exact name. A preview removed
 * through `preview down` must be named `preview-*`. Nothing else in the organisation is touched.
 */
final class Remover
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(private readonly Cloud $writer, private readonly Cloud $reader, private readonly array $manifest) {}

    /**
     * Deletes one custom domain of an owned environment.
     *
     * @return list<string>
     */
    public function removeDomain(string $environment, string $domain, ?string $confirm): array
    {
        if ($confirm !== $domain) {
            throw new PlanRefused("refusing to remove {$domain}: pass --confirm={$domain}");
        }

        $apps = $this->reader->list(['application:list']);
        Organisation::check($apps, $this->manifest);
        $app = Organisation::ownedApplication($apps, $this->manifest);

        foreach ($app === null ? [] : $this->reader->list(['environment:list', $app['id']]) as $env) {
            if ($env['name'] !== $environment) {
                continue;
            }

            foreach ($this->reader->list(['domain:list', $env['id']]) as $candidate) {
                if ($candidate['name'] === $domain) {
                    $this->writer->json(['domain:delete', $candidate['id'], '--force']);

                    return ["domain {$domain} removed from {$environment}"];
                }
            }

            return ["{$environment} has no domain {$domain}; nothing removed"];
        }

        return ["{$environment} does not exist in {$this->manifest['application']}; nothing removed"];
    }

    /**
     * @return list<string>
     */
    public function removeEnvironment(string $name, ?string $confirm, bool $previewOnly = false): array
    {
        if ($previewOnly && (! str_starts_with($name, 'preview-') || in_array($name, ['staging', 'production'], true))) {
            throw new PlanRefused("preview down removes only preview-* environments, not {$name}");
        }

        if ($confirm !== $name) {
            throw new PlanRefused("refusing to remove {$name}: pass --confirm={$name}");
        }

        $apps = $this->reader->list(['application:list']);
        Organisation::check($apps, $this->manifest);
        $app = Organisation::ownedApplication($apps, $this->manifest);
        $envs = $app === null ? [] : $this->reader->list(['environment:list', $app['id']]);
        $env = null;

        foreach ($envs as $candidate) {
            if ($candidate['name'] === $name) {
                $env = $candidate;
            }
        }

        if ($env === null) {
            return ["{$name} does not exist in {$this->manifest['application']}; nothing removed"];
        }

        if ($env['id'] === ($app['defaultEnvironmentId'] ?? null)) {
            throw new PlanRefused("{$name} is the application's default environment, which Cloud does not let you delete; the plan for its kind adopts it instead");
        }

        $schemaId = $env['databaseSchemaId'] ?? null;
        $cluster = null;

        if ($schemaId !== null) {
            $owned = array_map(fn ($d) => $d['name'], $this->manifest['databases'] ?? []);

            foreach ($this->reader->list(['database-cluster:list']) as $candidate) {
                if (in_array($candidate['name'], $owned, true) && in_array($schemaId, array_column($candidate['schemas'] ?? [], 'id'), true)) {
                    $cluster = $candidate;
                }
            }

            foreach ($apps as $other) {
                $others = $other['id'] === $app['id'] ? $envs : $this->reader->list(['environment:list', $other['id']]);

                foreach ($others as $candidate) {
                    if ($candidate['id'] !== $env['id'] && ($candidate['databaseSchemaId'] ?? null) === $schemaId) {
                        $cluster = null;
                    }
                }
            }
        }

        $this->writer->json(['environment:delete', $env['id'], '--force']);
        $log = ["environment {$name} ({$env['id']}) deleted"];

        if ($cluster !== null) {
            try {
                $this->writer->json(['database:delete', $cluster['id'], $schemaId, '--force']);
            } catch (CloudError $e) {
                throw new CloudError(implode("\n", array_merge($log, ["schema {$schemaId} was NOT deleted: {$e->getMessage()}", "Run remove again once the environment has gone, or delete the schema from cluster {$cluster['name']}."])));
            }

            $log[] = "schema {$schemaId} deleted from cluster {$cluster['name']}";
        } elseif ($schemaId !== null) {
            $log[] = "schema {$schemaId} kept: it is not in a cluster the manifest names, or another environment uses it";
        }

        return $log;
    }
}
