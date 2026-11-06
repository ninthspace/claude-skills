<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Reads Cloud and the manifest and returns the ordered steps that would bring one
 * environment to what the manifest declares. It makes no write call.
 *
 * A step's arguments may hold `{step:N.path}`, the value at `path` in the JSON step N
 * returned, for an ID that does not exist yet; `apply` resolves them. A variable's
 * argument is `{var:KEY}`, read from the manifest at apply time, so no value is in the plan.
 *
 * `$ownedOnly` and `$digestValues` exist so the tests can show what each guard prevents:
 * with the first off, resources of other applications are matched by name; with the
 * second off, variable values are stored instead of digests.
 */
final class Planner
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(
        private readonly Cloud $cloud,
        private readonly array $manifest,
        private readonly bool $ownedOnly = true,
        private readonly bool $digestValues = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(string $kind, ?string $slug = null, ?string $branchOption = null): array
    {
        [$name, $branch] = $this->identity($kind, $slug, $branchOption);

        $apps = $this->cloud->list(['application:list']);
        Organisation::check($apps, $this->manifest);

        $m = $this->manifest;
        $app = Organisation::ownedApplication($apps, $m);
        $envs = $app === null ? [] : $this->cloud->list(['environment:list', $app['id']]);
        $env = $this->named($envs, $name);

        if (! $this->ownedOnly) {
            foreach ($apps as $other) {
                $env ??= $this->named($other['environments'] ?? [], $name);
            }
        }

        $clusterName = isset($m['kinds'][$kind]['database']) ? $m['databases'][$m['kinds'][$kind]['database']]['name'] : null;
        $clusters = $clusterName === null ? [] : $this->cloud->list(['database-cluster:list']);
        $cluster = $this->named($clusters, (string) $clusterName);
        $schema = $this->named($cluster['schemas'] ?? [], $name);

        if ($schema !== null) {
            $this->assertSchemaUnused($schema, $apps, $app, $envs, $env['id'] ?? null);
        }

        if (! $this->ownedOnly && $schema === null) {
            foreach ($clusters as $other) {
                $cluster ??= $other;
                $schema ??= $this->named($other['schemas'] ?? [], $name);
            }
        }

        $existingSecrets = array_column($this->cloud->list(['secret:list']), 'id');
        $allowed = $this->allowedSecrets($kind);

        foreach ($allowed as $secret) {
            if (! isset($secret['id'])) {
                throw new PlanRefused("{$secret['key']} ({$secret['class']}) has no secret ID yet; run `secrets` to create it");
            }

            if (! in_array($secret['id'], $existingSecrets, true)) {
                throw new PlanRefused("secret {$secret['id']} ({$secret['key']}) is in the manifest but not in the organisation");
            }
        }

        $instances = $env === null ? [] : $this->cloud->list(['instance:list', $env['id']]);
        $detail = $env === null ? [] : $this->cloud->object(['environment:get', $env['id']], true);
        $attached = $env === null ? [] : array_column($this->cloud->list(['environment-secret:list', $env['id']]), 'id');
        $domains = $env === null ? [] : $this->cloud->list(['domain:list', $env['id']]);

        $appInstance = null;
        $queue = null;

        foreach ($instances as $instance) {
            if (Organisation::isQueue($instance)) {
                $queue ??= $instance;
            } else {
                $appInstance ??= $instance;
            }
        }

        if ($env !== null && $appInstance === null && isset($m['instance'])) {
            throw new PlanRefused("environment {$name} has no app instance to configure; instance:list returned ".count($instances).' instance(s)');
        }

        $steps = [];
        $add = function (string $key, string $resource, string $action, ?string $command, array $args, string $note = '', array $extra = []) use (&$steps): int {
            if ($command !== null) {
                Catalog::assertEmittable($command, $args);
            }

            $steps[] = array_merge(['n' => count($steps) + 1, 'key' => $key, 'resource' => $resource, 'action' => $action, 'command' => $command, 'args' => $args, 'note' => $note], $extra);

            return count($steps);
        };
        $ref = static fn (int $n, string $path = 'id'): string => "{step:{$n}.{$path}}";

        $appRef = $app['id'] ?? null;
        $n = $add('application', 'application', $app === null ? 'create' : 'keep', $app === null ? 'application:create' : null,
            $app === null ? ['--name='.$m['application'], '--repository='.$m['repository'], '--region='.$m['region']] : [], $m['application']);
        $appRef ??= $ref($n);

        $add('default-environment', 'default-environment', $app === null ? 'create' : 'keep', null, [],
            'Cloud creates a default environment, named production, with the application; it cannot be deleted, and the production plan adopts it.');

        $clusterRef = $cluster['id'] ?? null;
        $schemaRef = $schema['id'] ?? null;

        if ($clusterName !== null) {
            $n = $add('cluster', 'database-cluster', $cluster === null ? 'create' : 'keep', $cluster === null ? 'database-cluster:create' : null,
                $cluster === null ? $this->clusterCreateArgs($m['databases'][$m['kinds'][$kind]['database']], $m['region']) : [], $clusterName);
            $clusterRef ??= $ref($n);
            $this->clusterSettings($add, $m['databases'][$m['kinds'][$kind]['database']], $cluster, $clusterRef);

            $n = $add('schema', 'database', $schema === null ? 'create' : 'keep', $schema === null ? 'database:create' : null,
                $schema === null ? [$clusterRef, '--name='.$name] : [], $name);
            $schemaRef ??= $ref($n);
        }

        $envRef = $env['id'] ?? null;
        $n = $add('environment', 'environment', $env === null ? 'create' : 'keep', $env === null ? 'environment:create' : null,
            $env === null ? [$appRef, '--name='.$name, '--branch='.$branch] : [], "{$name} on {$branch}");
        $envRef ??= $ref($n);
        $envStep = $n;

        $wanted = ['--branch' => $branch];

        foreach (['--build-command' => 'build_command', '--deploy-command' => 'deploy_command'] as $option => $field) {
            $command = Manifest::commandFor($m, $kind, $field);

            if ($command !== null) {
                $wanted[$option] = $command;
            }
        }

        $current = ['--branch' => $env['branch'] ?? null, '--build-command' => $env['buildCommand'] ?? null, '--deploy-command' => $env['deployCommand'] ?? null];

        if ($schemaRef !== null) {
            $wanted['--database-id'] = $schemaRef;
            $current['--database-id'] = $env['databaseSchemaId'] ?? null;
        }

        if (isset($m['kinds'][$kind]['push_to_deploy'])) {
            $wanted['--uses-push-to-deploy'] = json_encode($m['kinds'][$kind]['push_to_deploy']);
            $current['--uses-push-to-deploy'] = isset($env['usesPushToDeploy']) ? json_encode($env['usesPushToDeploy']) : null;
        }

        $differing = array_filter($wanted, fn ($value, $option) => $env === null || ($current[$option] ?? null) !== $value, ARRAY_FILTER_USE_BOTH);
        $settingsArgs = [$envRef];

        foreach ($differing as $option => $value) {
            $settingsArgs[] = "{$option}={$value}";
        }

        $add('environment-settings', 'environment', $differing === [] ? 'keep' : 'change', $differing === [] ? null : 'environment:update',
            $differing === [] ? [] : array_merge($settingsArgs, ['--force']), 'branch, build and deploy commands, database, push to deploy');

        if (isset($m['instance'])) {
            $i = $m['instance'];
            $differs = $env === null || $appInstance === null
                || ($appInstance['size'] ?? null) !== $i['size']
                || ($appInstance['usesScheduler'] ?? null) !== ($i['scheduler'] ?? false)
                || ($detail['usesHibernation'] ?? null) !== ($i['scale_to_zero'] ?? false)
                || (($i['scale_to_zero'] ?? false) && isset($i['sleep_timeout']) && ($appInstance['hibernationTimeout'] ?? null) !== $i['sleep_timeout']);
            $instanceRef = $appInstance['id'] ?? $ref($envStep, 'instances.0');
            $args = [$instanceRef, '--size='.$i['size'], '--uses-scheduler='.json_encode($i['scheduler'] ?? false), '--scale-to-zero='.json_encode($i['scale_to_zero'] ?? false)];

            if (isset($i['sleep_timeout'])) {
                $args[] = '--scale-to-zero-timeout='.$i['sleep_timeout'];
            }

            $add('instance', 'instance', $differs ? 'change' : 'keep', $differs ? 'instance:update' : null, $differs ? array_merge($args, ['--force']) : [], 'size, scheduler, scale to zero and its timeout');
        }

        if (isset($m['queue'])) {
            $q = $m['queue'];

            if ($queue === null) {
                $n = $add('queue', 'managed-queue', 'create', 'managed-queue:create', [$envRef, '--name='.($q['name'] ?? 'default'), '--size='.$q['size'], '--max-workers='.$q['max_workers']], 'managed queue');
                $queueRef = $ref($n);
            } else {
                $differs = ($queue['size'] ?? null) !== $q['size'] || ($queue['maxReplicas'] ?? null) !== $q['max_workers'];
                $add('queue', 'managed-queue', $differs ? 'change' : 'keep', $differs ? 'instance:update' : null,
                    $differs ? [$queue['id'], '--size='.$q['size'], '--max-replicas='.$q['max_workers'], '--force'] : [], 'managed queue');
                $queueRef = $queue['id'];
            }

            // Creating a queue takes no minimum, and Cloud sets 1, so the minimum is its own step.
            if (isset($q['min_workers'])) {
                $differs = $queue === null || ($queue['minReplicas'] ?? null) !== $q['min_workers'];
                $add('queue-scaling', 'managed-queue', $differs ? 'change' : 'keep', $differs ? 'instance:update' : null,
                    $differs ? [$queueRef, '--min-replicas='.$q['min_workers'], '--force'] : [], 'minimum workers');
            }
        }

        $currentVariables = [];

        foreach ($detail['environmentVariables'] ?? [] as $variable) {
            $currentVariables[$variable['key']] = $this->digest((string) $variable['value']);
        }

        $variables = Manifest::variablesFor($m, $kind);
        ksort($variables);
        $variableState = [];

        foreach ($variables as $key => $value) {
            $desired = $this->digest($value);
            $present = array_key_exists($key, $currentVariables);
            $variableState[$key] = $currentVariables[$key] ?? null;
            $action = ! $present ? 'create' : ($currentVariables[$key] === $desired ? 'keep' : 'change');

            $add("variable:{$key}", 'variable', $action, $action === 'keep' ? null : 'environment:variables',
                $action === 'keep' ? [] : [$envRef, '--action='.($present ? 'set' : 'append'), '--key='.$key, '--value={var:'.$key.'}', '--force'], $key, ['digest' => $desired]);
        }

        foreach ($allowed as $secret) {
            $isAttached = in_array($secret['id'], $attached, true);
            $add("secret:{$secret['id']}", 'secret', $isAttached ? 'keep' : 'create', $isAttached ? null : 'environment-secret:attach',
                $isAttached ? [] : [$envRef, $secret['id']], $secret['key'].' ('.$secret['class'].')');
        }

        // Domains are managed only for a kind that declares `domains`; otherwise a domain added
        // in the dashboard is left alone rather than planned for deletion.
        $managesDomains = array_key_exists('domains', $m['kinds'][$kind]);
        $wantedDomains = $m['kinds'][$kind]['domains'] ?? [];
        $domainNames = array_column($domains, 'name');

        foreach ($wantedDomains as $domain) {
            $has = in_array($domain, $domainNames, true);
            $add("domain:{$domain}", 'domain', $has ? 'keep' : 'create', $has ? null : 'domain:create', $has ? [] : [$envRef, '--name='.$domain], $domain);
        }

        foreach ($managesDomains ? $domains : [] as $domain) {
            if (! in_array($domain['name'], $wantedDomains, true)) {
                $add("domain:{$domain['name']}", 'domain', 'delete', 'domain:delete', [$domain['id'], '--force'], $domain['name']);
            }
        }

        $attachedSorted = $attached;
        sort($attachedSorted);

        return [
            'version' => 1,
            'kind' => $kind,
            'environment' => $name,
            'branch' => $branch,
            'organisation' => $m['organisation'],
            'application' => $m['application'],
            'region' => $m['region'],
            'steps' => $steps,
            'secrets' => array_map(fn ($s) => ['id' => $s['id'] ?? null, 'key' => $s['key'], 'class' => $s['class'], 'kinds' => $s['kinds']], $m['secrets'] ?? []),
            'resources' => array_filter([
                'push_to_deploy' => $m['kinds'][$kind]['push_to_deploy'] ?? null,
                'instance' => isset($m['instance']) ? array_filter(['size' => $m['instance']['size'], 'scheduler' => $m['instance']['scheduler'] ?? false, 'scale_to_zero' => $m['instance']['scale_to_zero'] ?? false, 'sleep_timeout' => $m['instance']['sleep_timeout'] ?? null], fn ($v) => $v !== null) : null,
                'queue' => isset($m['queue']) ? array_filter(['name' => $m['queue']['name'] ?? 'default', 'size' => $m['queue']['size'], 'min_workers' => $m['queue']['min_workers'] ?? null, 'max_workers' => $m['queue']['max_workers']], fn ($v) => $v !== null) : null,
                'database' => $clusterName === null ? null : array_filter(['name' => $clusterName, 'type' => $m['databases'][$m['kinds'][$kind]['database']]['type'], 'size' => $m['databases'][$m['kinds'][$kind]['database']]['size'] ?? null, 'storage' => $m['databases'][$m['kinds'][$kind]['database']]['storage'] ?? null, 'scale_to_zero_after' => $m['databases'][$m['kinds'][$kind]['database']]['scale_to_zero_after'] ?? null], fn ($v) => $v !== null),
            ], fn ($v) => $v !== null),
            'state' => [
                'application' => $app === null ? null : ['id' => $app['id'], 'defaultEnvironmentId' => $app['defaultEnvironmentId'] ?? null],
                'cluster' => $cluster === null ? null : array_merge(['id' => $cluster['id']], self::clusterSettingsNow($m['databases'][$m['kinds'][$kind]['database']], $cluster)),
                'schema' => $schema === null ? null : ['id' => $schema['id']],
                'environment' => $env === null ? null : array_filter(['usesPushToDeploy' => isset($m['kinds'][$kind]['push_to_deploy']) ? ($env['usesPushToDeploy'] ?? null) : null, 'id' => $env['id'], 'branch' => $env['branch'] ?? null, 'buildCommand' => Manifest::commandFor($m, $kind, 'build_command') !== null ? ($env['buildCommand'] ?? null) : null, 'deployCommand' => Manifest::commandFor($m, $kind, 'deploy_command') !== null ? ($env['deployCommand'] ?? null) : null, 'databaseSchemaId' => $env['databaseSchemaId'] ?? null], fn ($v) => $v !== null),
                'instance' => $appInstance === null ? null : ['id' => $appInstance['id'], 'size' => $appInstance['size'] ?? null, 'usesScheduler' => $appInstance['usesScheduler'] ?? null, 'usesHibernation' => $detail['usesHibernation'] ?? null, 'hibernationTimeout' => $appInstance['hibernationTimeout'] ?? null],
                'queue' => $queue === null ? null : array_filter(['id' => $queue['id'], 'size' => $queue['size'] ?? null, 'maxReplicas' => $queue['maxReplicas'] ?? null, 'minReplicas' => isset($m['queue']['min_workers']) ? ($queue['minReplicas'] ?? null) : null], fn ($v) => $v !== null),
                'variables' => $variableState,
                'secrets' => $attachedSorted,
                'domains' => array_map(fn ($d) => ['id' => $d['id'], 'name' => $d['name']], $domains),
            ],
        ];
    }

    /**
     * Adds the cluster-settings step when the manifest declares a size, storage or scale to
     * zero for the cluster: a change for a new cluster or differing settings, else a keep.
     *
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>|null  $cluster
     */
    private function clusterSettings(\Closure $add, array $database, ?array $cluster, string $clusterRef): void
    {
        $wanted = array_filter(['--size' => $database['size'] ?? null, '--storage' => $database['storage'] ?? null, '--suspend-seconds' => $database['scale_to_zero_after'] ?? null], fn ($v) => $v !== null);

        // A new cluster is created with its settings in one call (clusterCreateArgs).
        if ($wanted === [] || $cluster === null) {
            return;
        }

        $now = $cluster === null ? null : self::clusterSettingsNow($database, $cluster);
        $differs = $cluster === null
            || (isset($wanted['--size']) && self::sizeKey((string) $wanted['--size']) !== self::sizeKey((string) ($now['size'] ?? '')))
            || (isset($wanted['--storage']) && (int) $wanted['--storage'] !== ($now['storage'] ?? null))
            || (isset($wanted['--suspend-seconds']) && (int) $wanted['--suspend-seconds'] !== ($now['suspend_seconds'] ?? null));
        $args = [$clusterRef];

        foreach ($wanted as $option => $value) {
            $args[] = "{$option}={$value}";
        }

        $add('cluster-settings', 'database-cluster', $differs ? 'change' : 'keep', $differs ? 'database-cluster:update' : null, $differs ? array_merge($args, ['--force']) : [], 'size, storage, scale to zero');
    }

    /**
     * @param  array<string, mixed>  $database
     * @return list<string>
     */
    private function clusterCreateArgs(array $database, string $region): array
    {
        $args = ['--name='.$database['name'], '--type='.$database['type'], '--region='.$region];

        foreach (['size' => '--size', 'storage' => '--storage', 'scale_to_zero_after' => '--suspend-seconds'] as $field => $option) {
            if (isset($database[$field])) {
                $args[] = "{$option}={$database[$field]}";
            }
        }

        // Each environment takes its own schema, so the cluster's default database is not wanted.
        $args[] = '--create-default-database=false';

        return $args;
    }

    /**
     * The cluster settings the manifest declares, as Cloud reports them now.
     *
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $cluster
     * @return array<string, mixed>
     */
    public static function clusterSettingsNow(array $database, array $cluster): array
    {
        $config = $cluster['config'] ?? [];

        return array_filter([
            'size' => isset($database['size']) ? ($config['size'] ?? null) : null,
            'storage' => isset($database['storage']) ? ($config['storage'] ?? null) : null,
            'suspend_seconds' => isset($database['scale_to_zero_after']) ? ($config['suspend_seconds'] ?? null) : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * A size reduced to tier and memory, so the CLI's `db-flex.m-1vcpu-512mb` and Cloud's
     * read-back `mysql-flex-512mb` compare equal.
     */
    public static function sizeKey(string $size): string
    {
        return preg_match('/(flex|pro)\b.*?(\d+(?:mb|gb))$/', $size, $m) === 1 ? "{$m[1]}-{$m[2]}" : $size;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function identity(string $kind, ?string $slug, ?string $branchOption): array
    {
        $rule = $this->manifest['kinds'][$kind]['branch'] ?? null;

        if ($rule === null) {
            throw new PlanRefused("the manifest has no kind {$kind}");
        }

        if ($kind === 'preview') {
            if ($slug === null || preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
                throw new PlanRefused('a preview needs --slug (lower-case letters, digits and hyphens)');
            }

            return ["preview-{$slug}", $branchOption ?? ($rule['rule'] === 'fixed' ? $rule['name'] : $slug)];
        }

        if ($slug !== null || $branchOption !== null) {
            throw new PlanRefused("{$kind} takes no --slug or --branch; its branch is {$rule['name']}");
        }

        return [$kind, $rule['name']];
    }

    /**
     * A schema found by name may already belong to another environment (of this or another
     * application in the organisation); attaching it would run migrations on that data.
     *
     * Every application's environments are read with `environment:list`: the environments
     * `application:list` embeds carry no `databaseSchemaId`.
     *
     * @param  array<string, mixed>  $schema
     * @param  list<array<string, mixed>>  $apps
     * @param  array<string, mixed>|null  $owned
     * @param  list<array<string, mixed>>  $envs  the owned application's environments, already read
     */
    private function assertSchemaUnused(array $schema, array $apps, ?array $owned, array $envs, ?string $ownEnvironmentId): void
    {
        $candidates = $envs;

        foreach ($apps as $app) {
            if ($owned === null || $app['id'] !== $owned['id']) {
                $candidates = array_merge($candidates, $this->cloud->list(['environment:list', $app['id']]));
            }
        }

        foreach ($candidates as $candidate) {
            if (($candidate['databaseSchemaId'] ?? null) === $schema['id'] && $candidate['id'] !== $ownEnvironmentId) {
                throw new PlanRefused("schema {$schema['name']} ({$schema['id']}) is already used by environment {$candidate['name']} ({$candidate['id']}); refusing to attach it");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function named(array $items, string $name): ?array
    {
        foreach ($items as $item) {
            if (($item['name'] ?? null) === $name) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allowedSecrets(string $kind): array
    {
        return array_values(array_filter($this->manifest['secrets'] ?? [], fn ($s) => in_array($kind, $s['kinds'], true)));
    }

    private function digest(string $value): string
    {
        return $this->digestValues ? hash('sha256', $value) : $value;
    }
}
