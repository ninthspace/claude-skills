<?php

declare(strict_types=1);

/**
 * Builds Cloud state for the stub: one JSON file per command, in the shapes `cloud --json`
 * prints (every key of the real objects, with IDs and names replaced).
 */
final class State
{
    public string $dir;

    /** @var array<string, mixed> */
    public array $files = [];

    public function __construct()
    {
        $this->dir = tempDir();
        $this->files['application:list'] = [];
        $this->files['database-cluster:list'] = [];
        $this->files['secret:list'] = [];
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    public static function app(string $id, string $name, string $org = 'example-org', array $over = []): array
    {
        return array_merge([
            'id' => $id, 'name' => $name, 'slug' => $name, 'region' => 'eu-west-2', 'environmentIds' => [], 'deploymentIds' => [],
            'repositoryFullName' => 'example-owner/'.$name, 'repositoryBranch' => 'main', 'slackChannel' => null, 'avatarUrl' => null,
            'rootDirectory' => null, 'createdAt' => '2026-01-01T00:00:00.000000Z', 'repositoryId' => null, 'organizationId' => 'org-1',
            'defaultEnvironmentId' => null, 'organization' => ['id' => 'org-1', 'name' => $org, 'slug' => $org], 'environments' => [], 'deployments' => [],
        ], $over);
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    public static function env(string $id, string $name, string $branch, array $over = []): array
    {
        return array_merge([
            'id' => $id, 'url' => "https://{$name}.example.test", 'name' => $name, 'branch' => $branch, 'status' => 'running', 'instances' => [],
            'buildCommand' => 'build', 'deployCommand' => 'deploy', 'slug' => $name, 'statusEnum' => 'running', 'createdFromAutomation' => false,
            'vanityDomain' => "{$name}.example.test", 'phpMajorVersion' => '8.5', 'nodeVersion' => '22', 'usesOctane' => false, 'usesHibernation' => true,
            'usesPushToDeploy' => false, 'usesDeployHook' => false, 'environmentVariables' => [], 'networkSettings' => [], 'createdAt' => '2026-01-01T00:00:00.000000Z',
            'updatedAt' => null, 'application' => null, 'branchId' => null, 'deploymentIds' => [], 'currentDeploymentId' => null, 'domainIds' => [],
            'primaryDomainId' => null, 'databaseSchemaId' => null, 'cacheId' => null, 'websocketApplicationId' => null, 'secrets' => [],
        ], $over);
    }

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    public static function instance(string $id, string $size, array $over = []): array
    {
        return array_merge([
            'id' => $id, 'name' => 'app', 'type' => 'app', 'size' => $size, 'scalingType' => 'none', 'minReplicas' => 1, 'maxReplicas' => 1, 'usesScheduler' => true,
            'scalingCpuThresholdPercentage' => null, 'scalingMemoryThresholdPercentage' => null, 'queueStatus' => null, 'paused' => null, 'isDefault' => null,
            'visibilityTimeout' => null, 'pollingInterval' => null, 'createdAt' => '2026-01-01T00:00:00.000000Z', 'updatedAt' => null, 'environment' => null, 'backgroundProcessIds' => [],
            'hibernationTimeout' => null,
        ], $over);
    }

    /**
     * @param  list<array<string, mixed>>  $schemas
     * @return array<string, mixed>
     */
    public static function cluster(string $id, string $name, array $schemas = []): array
    {
        return [
            'id' => $id, 'name' => $name, 'type' => 'laravel_mysql', 'status' => 'available', 'region' => 'eu-west-2',
            'config' => ['size' => 'small', 'storage' => 5, 'is_public' => false, 'uses_scheduled_snapshots' => true, 'retention_days' => 7, 'suspend_seconds' => 300, 'storage_autoscale_max_gb' => null],
            'connection' => ['hostname' => 'db.example.test', 'port' => 3306, 'protocol' => 'mysql', 'driver' => 'mysql', 'username' => 'user', 'password' => '********'],
            'createdAt' => '2026-01-01T00:00:00.000000Z', 'updatedAt' => null, 'schemas' => $schemas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function secret(string $id, string $key): array
    {
        return ['id' => $id, 'key' => $key, 'notes' => 'test', 'createdAt' => '2026-01-01T00:00:00.000000Z', 'updatedAt' => '2026-01-01T00:00:00.000000Z'];
    }

    public function put(string $name, mixed $data): self
    {
        $this->files[$name] = $data;

        return $this;
    }

    public function fail(string $command, int $exit, string $message): self
    {
        $this->files["{$command}.exit"] = (string) $exit;
        $this->files["{$command}.stderr"] = $message;

        return $this;
    }

    /**
     * Writes the files and returns the environment for a process that should use them.
     *
     * @return array<string, string>
     */
    public function processEnv(): array
    {
        foreach ($this->files as $name => $data) {
            file_put_contents("{$this->dir}/{$name}".(str_contains($name, '.exit') || str_contains($name, '.stderr') ? '' : '.json'), is_string($data) ? $data : json_encode($data));
        }

        return ['CLOUD_BIN' => skillDir().'/tests/stub/cloud', 'CLOUD_STUB_FIXTURES' => $this->dir, 'CLOUD_STUB_LOG' => $this->dir.'/calls.log', 'CLOUD_STUB_WORLD' => '', 'CLOUD_STUB_FAIL' => ''];
    }

    /**
     * @return list<array{argv: list<string>, write: bool}>
     */
    public function calls(): array
    {
        $log = "{$this->dir}/calls.log";

        return is_file($log) ? array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    }

    /**
     * Applies the state to this process and returns a runner against the stub.
     */
    public function cloud(bool $writes = false): \CloudEnvironments\Cloud
    {
        foreach ($this->processEnv() as $key => $value) {
            putenv("{$key}={$value}");
        }

        return \CloudEnvironments\Cloud::fromEnvironment($writes);
    }
}

/**
 * The state of an organisation that already holds the manifest's application, with the
 * environment `preview-x` complete as the valid manifest declares it.
 */
function completePreviewState(): State
{
    $state = new State();
    $schema = ['id' => 'schema-1', 'name' => 'preview-x', 'createdAt' => '2026-01-01T00:00:00.000000Z'];
    $env = State::env('env-1', 'preview-x', 'x', ['databaseSchemaId' => 'schema-1', 'buildCommand' => validManifest()['build_command'], 'deployCommand' => validManifest()['deploy_command'], 'instances' => ['inst-1']]);

    $state->put('application:list', [State::app('app-1', 'example-app', 'example-org', ['defaultEnvironmentId' => 'env-0', 'environments' => [$env]])])
        ->put('environment:list@app-1', [$env])
        ->put('database-cluster:list', [State::cluster('cluster-1', 'example-nonprod', [$schema])])
        ->put('secret:list', [State::secret('secret-0001', 'SERVICE_PASSWORD'), State::secret('secret-0002', 'SERVICE_PASSWORD')])
        ->put('instance:list@env-1', [
            State::instance('inst-1', 'flex-1gb', ['hibernationTimeout' => 5]),
            State::instance('queue-1', 'mq.flex.256mb', ['name' => 'default', 'type' => 'queue', 'queueStatus' => 'running', 'maxReplicas' => 3, 'visibilityTimeout' => 60]),
        ])
        ->put('environment:get@env-1', State::env('env-1', 'preview-x', 'x', ['environmentVariables' => [
            ['key' => 'APP_ENV', 'value' => 'local'], ['key' => 'APP_DEBUG', 'value' => 'true'], ['key' => 'MAIL_MAILER', 'value' => 'log'],
        ], 'usesHibernation' => true]))
        ->put('environment-secret:list@env-1', [State::secret('secret-0001', 'SERVICE_PASSWORD')])
        ->put('domain:list@env-1', []);

    return $state;
}
