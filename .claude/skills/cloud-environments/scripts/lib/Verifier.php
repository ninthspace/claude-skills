<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Compares a built environment with the manifest and returns one message per difference.
 * Variables the manifest does not declare are ignored.
 */
final class Verifier
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(private readonly Cloud $reader, private readonly array $manifest, private readonly ?Cloud $runner = null) {}

    /**
     * @return list<string>
     */
    public function verify(string $environment, ?string $branch = null, bool $checkExtensions = true): array
    {
        $m = $this->manifest;
        $kind = Organisation::kindOf($environment);

        if ($kind === null || ! isset($m['kinds'][$kind])) {
            throw new PlanRefused("{$environment} is not a preview, staging or production environment of this manifest");
        }

        if ($kind !== 'preview' && $branch !== null) {
            throw new PlanRefused("{$kind} takes no --branch; its branch is {$m['kinds'][$kind]['branch']['name']}");
        }

        $apps = $this->reader->list(['application:list']);
        Organisation::check($apps, $m);
        $app = Organisation::ownedApplication($apps, $m);
        $env = null;

        foreach ($app === null ? [] : $this->reader->list(['environment:list', $app['id']]) as $candidate) {
            if ($candidate['name'] === $environment) {
                $env = $candidate;
            }
        }

        if ($env === null) {
            return ["environment {$environment} does not exist in {$m['application']}"];
        }

        $failures = [];
        $detail = $this->reader->object(['environment:get', $env['id']], true);
        $rule = $m['kinds'][$kind]['branch'];
        $expectedBranch = $branch ?? ($rule['rule'] === 'fixed' ? $rule['name'] : substr($environment, strlen('preview-')));

        if (($env['branch'] ?? null) !== $expectedBranch) {
            $failures[] = "branch: {$env['branch']}, expected {$expectedBranch}";
        }

        if (isset($m['kinds'][$kind]['push_to_deploy']) && ($env['usesPushToDeploy'] ?? null) !== $m['kinds'][$kind]['push_to_deploy']) {
            $failures[] = 'push to deploy: '.json_encode($env['usesPushToDeploy'] ?? null).', expected '.json_encode($m['kinds'][$kind]['push_to_deploy']);
        }

        if (isset($m['php']['version']) && (string) ($detail['phpMajorVersion'] ?? '') !== (string) $m['php']['version']) {
            $failures[] = 'PHP version: '.($detail['phpMajorVersion'] ?? 'unknown').", expected {$m['php']['version']}";
        }

        if ($checkExtensions && ($m['php']['extensions'] ?? []) !== []) {
            if ($this->runner === null) {
                throw new PlanRefused('checking extensions runs `php -m` in the environment; pass a runner that may run commands, or skip the check');
            }

            $run = $this->runner->lastObject(['command:run', $env['id'], '--cmd=php -m']);

            if (($run['status'] ?? null) !== 'command.success') {
                $failures[] = 'php -m did not succeed: '.($run['status'] ?? 'no status');
            }
            $loaded = array_map(fn ($line) => strtolower(trim($line)), explode("\n", (string) ($run['output'] ?? '')));

            foreach ($m['php']['extensions'] as $extension) {
                if (! in_array(strtolower($extension), $loaded, true)) {
                    $failures[] = "PHP extension {$extension} is not loaded";
                }
            }
        }

        $live = [];

        foreach ($detail['environmentVariables'] ?? [] as $variable) {
            $live[$variable['key']] = (string) $variable['value'];
        }

        foreach (Manifest::variablesFor($m, $kind) as $key => $value) {
            if (! array_key_exists($key, $live)) {
                $failures[] = "variable {$key} is missing";
            } elseif (! hash_equals(hash('sha256', $value), hash('sha256', $live[$key]))) {
                $failures[] = "variable {$key} differs from the manifest";
            }
        }

        foreach (Manifest::requiredFor($m, $kind) as $key => $required) {
            $value = $live[$key] ?? null;

            if (isset($required['equals']) && $value !== $required['equals']) {
                $failures[] = "required value: {$key} must be {$required['equals']}";
            } elseif (isset($required['not']) && ($value === null || $value === $required['not'])) {
                $failures[] = "required value: {$key} must be set and not {$required['not']}";
            }
        }

        $attached = array_column($this->reader->list(['environment-secret:list', $env['id']]), 'id');
        $byId = [];

        foreach ($m['secrets'] ?? [] as $secret) {
            if (! isset($secret['id'])) {
                if (in_array($kind, $secret['kinds'], true)) {
                    $failures[] = "secret {$secret['key']} ({$secret['class']}) has no ID; run `secrets`";
                }

                continue;
            }

            $byId[$secret['id']] = $secret;
        }

        foreach ($attached as $id) {
            if (! isset($byId[$id]) || ! in_array($kind, $byId[$id]['kinds'], true)) {
                $failures[] = "secret {$id} is attached but not allowed on a {$kind}";
            }
        }

        foreach ($byId as $id => $secret) {
            if (in_array($kind, $secret['kinds'], true) && ! in_array($id, $attached, true)) {
                $failures[] = "secret {$secret['key']} ({$id}) is not attached";
            }
        }

        $instances = $this->reader->list(['instance:list', $env['id']]);
        $appInstance = null;
        $queue = null;

        foreach ($instances as $instance) {
            if (Organisation::isQueue($instance)) {
                $queue ??= $instance;
            } else {
                $appInstance ??= $instance;
            }
        }

        if (isset($m['instance'])) {
            $i = $m['instance'];

            if ($appInstance === null) {
                $failures[] = 'instance: no app instance';
            } else {
                if (($appInstance['size'] ?? null) !== $i['size']) {
                    $failures[] = "instance size: {$appInstance['size']}, expected {$i['size']}";
                }

                if (($appInstance['usesScheduler'] ?? null) !== ($i['scheduler'] ?? false)) {
                    $failures[] = 'instance scheduler differs from the manifest';
                }
            }

            if (($detail['usesHibernation'] ?? null) !== ($i['scale_to_zero'] ?? false)) {
                $failures[] = 'scale to zero differs from the manifest';
            } elseif (($i['scale_to_zero'] ?? false) && isset($i['sleep_timeout']) && $appInstance !== null && ($appInstance['hibernationTimeout'] ?? null) !== $i['sleep_timeout']) {
                $failures[] = 'scale to zero timeout: '.($appInstance['hibernationTimeout'] ?? 'unknown').' minutes, expected '.$i['sleep_timeout'];
            }
        }

        if (isset($m['queue'])) {
            if ($queue === null) {
                $failures[] = 'managed queue is missing';
            } elseif (($queue['size'] ?? null) !== $m['queue']['size'] || ($queue['maxReplicas'] ?? null) !== $m['queue']['max_workers']) {
                $failures[] = 'managed queue size or workers differ from the manifest';
            } elseif (isset($m['queue']['min_workers']) && ($queue['minReplicas'] ?? null) !== $m['queue']['min_workers']) {
                $failures[] = 'managed queue minimum workers: '.($queue['minReplicas'] ?? 'unknown').', expected '.$m['queue']['min_workers'];
            }
        }

        if (isset($m['kinds'][$kind]['database'])) {
            $clusterName = $m['databases'][$m['kinds'][$kind]['database']]['name'];
            $schemaIds = [];

            $owned = null;

            foreach ($this->reader->list(['database-cluster:list']) as $cluster) {
                if ($cluster['name'] === $clusterName) {
                    $schemaIds = array_column($cluster['schemas'] ?? [], 'id');
                    $owned = $cluster;
                }
            }

            $database = $m['databases'][$m['kinds'][$kind]['database']];
            $now = $owned === null ? [] : Planner::clusterSettingsNow($database, $owned);

            if (isset($database['size']) && Planner::sizeKey((string) $database['size']) !== Planner::sizeKey((string) ($now['size'] ?? ''))) {
                $failures[] = 'database size: '.($now['size'] ?? 'unknown').", expected {$database['size']}";
            }

            if (isset($database['storage']) && $database['storage'] !== ($now['storage'] ?? null)) {
                $failures[] = 'database storage: '.($now['storage'] ?? 'unknown')." GB, expected {$database['storage']}";
            }

            if (isset($database['scale_to_zero_after']) && $database['scale_to_zero_after'] !== ($now['suspend_seconds'] ?? null)) {
                $failures[] = 'database scale to zero: '.($now['suspend_seconds'] ?? 'unknown')." seconds, expected {$database['scale_to_zero_after']}";
            }

            if (! in_array($env['databaseSchemaId'] ?? null, $schemaIds, true)) {
                $failures[] = "database: the environment's schema is not in cluster {$clusterName}";
            }
        }

        return $failures;
    }
}
