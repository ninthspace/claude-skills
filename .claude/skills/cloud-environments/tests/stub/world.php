<?php

// A stateful stand-in for Cloud, used by the stub when CLOUD_STUB_WORLD names a JSON file.
// Reads answer from the file and writes change it, so a test can apply a plan and then plan
// again. CLOUD_STUB_FAIL names a command that fails. A created cluster reports `creating`
// for the first CLOUD_STUB_PENDING reads (default 1), then `available`.

/**
 * @param  list<string>  $argv
 */
function world(string $file, array $argv): never
{
    $w = json_decode((string) file_get_contents($file), true);
    $command = $argv[0];
    $positional = [];
    $opt = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $arg, $m) === 1) {
            $opt[$m[1]] = $m[2] ?? true;
        } else {
            $positional[] = $arg;
        }
    }

    if (getenv('CLOUD_STUB_FAIL') === $command) {
        // A failing secret:create repeats what it was given, as a careless CLI might.
        $given = $command === 'secret:create' ? ' for input '.stream_get_contents(STDIN) : '';
        fwrite(STDERR, (getenv('CLOUD_STUB_FAIL_MESSAGE') ?: "stub: {$command} failed as asked").$given."\n");
        exit(1);
    }

    $now = '2026-01-01T00:00:00.000000Z';
    $next = function (string $prefix) use (&$w): string {
        $w['seq'] = ($w['seq'] ?? 0) + 1;

        return "{$prefix}-w{$w['seq']}";
    };
    $save = function () use (&$w, $file): void {
        file_put_contents($file, json_encode($w));
    };
    $out = function (mixed $data) use ($save): never {
        $save();
        echo json_encode($data);
        exit(0);
    };
    $envIndex = function (string $id) use (&$w): int {
        foreach ($w['environments'] as $i => $e) {
            if ($e['id'] === $id) {
                return $i;
            }
        }
        fwrite(STDERR, "stub: no environment {$id}\n");
        exit(1);
    };
    $envView = function (array $e, bool $sensitive) use (&$w): array {
        $view = State::env($e['id'], $e['name'], $e['branch'], [
            'buildCommand' => $e['buildCommand'], 'deployCommand' => $e['deployCommand'], 'databaseSchemaId' => $e['databaseSchemaId'],
            'usesHibernation' => $e['usesHibernation'], 'phpMajorVersion' => $e['phpMajorVersion'], 'usesPushToDeploy' => $e['usesPushToDeploy'] ?? true,
            'instances' => array_values(array_map(fn ($i) => $i['id'], array_filter($w['instances'], fn ($i) => $i['env'] === $e['id']))),
            'environmentVariables' => array_map(fn ($k) => ['key' => $k, 'value' => $sensitive ? $e['variables'][$k] : '********'], array_keys($e['variables'])),
        ]);

        return $view;
    };

    switch ($command) {
        case 'application:list':
            $out(array_map(fn ($a) => State::app($a['id'], $a['name'], $w['organisation'], [
                'defaultEnvironmentId' => $a['defaultEnvironmentId'],
                'environments' => array_values(array_map(fn ($e) => State::env($e['id'], $e['name'], $e['branch']), array_filter($w['environments'], fn ($e) => $e['app'] === $a['id']))),
            ]), $w['applications']));
        case 'environment:list':
            $out(array_values(array_map(fn ($e) => $envView($e, false), array_filter($w['environments'], fn ($e) => $e['app'] === $positional[0]))));
        case 'environment:get':
            $i = $envIndex($positional[0]);
            $w['environments'][$i]['pending'] = ($w['environments'][$i]['pending'] ?? 0) - 1;
            $view = $envView($w['environments'][$i], isset($opt['show-sensitive']));

            if ($w['environments'][$i]['pending'] >= 0) {
                $view['instances'] = [];
            }

            $out($view);
        case 'instance:list':
            $out(array_values(array_map(fn ($i) => State::instance($i['id'], $i['size'], ['type' => $i['type'], 'name' => $i['name'], 'usesScheduler' => $i['usesScheduler'], 'maxReplicas' => $i['maxReplicas'], 'minReplicas' => $i['minReplicas'] ?? 1, 'hibernationTimeout' => $i['hibernationTimeout'] ?? null, 'queueStatus' => $i['type'] === 'managed_queue' ? 'running' : null]), array_filter($w['instances'], fn ($i) => $i['env'] === $positional[0]))));
        case 'database-cluster:list':
            foreach ($w['clusters'] as &$c) {
                if ($c['status'] === 'creating' && --$c['pending'] < 0) {
                    $c['status'] = 'available';
                }
            }
            unset($c);
            foreach ($w['clusters'] as &$c) {
                foreach ($c['schemas'] as &$s) {
                    $s['pending'] = ($s['pending'] ?? 0) - 1;
                }
                unset($s);
            }
            unset($c);
            $out(array_map(function ($c) use ($now) {
                $view = array_merge(State::cluster($c['id'], $c['name'], array_values(array_map(fn ($s) => ['id' => $s['id'], 'name' => $s['name'], 'createdAt' => $now], array_filter($c['schemas'], fn ($s) => ($s['pending'] ?? -1) < 0)))), ['status' => $c['status']]);
                $view['config'] = array_merge($view['config'], $c['config'] ?? []);

                return $view;
            }, $w['clusters']));
        case 'database-cluster:update':
            foreach ($w['clusters'] as &$c) {
                if ($c['id'] === $positional[0]) {
                    // Cloud reports a size under its own name, `mysql-flex-512mb` for `db-flex.m-1vcpu-512mb`.
                    if (isset($opt['size'])) {
                        $c['config']['size'] = preg_replace('/^db-(flex|pro)\.m-\d+vcpu-/', 'mysql-$1-', $opt['size']);
                    }
                    if (isset($opt['storage'])) {
                        $c['config']['storage'] = (int) $opt['storage'];
                    }
                    if (isset($opt['suspend-seconds'])) {
                        $c['config']['suspend_seconds'] = (int) $opt['suspend-seconds'];
                    }
                }
            }
            unset($c);
            $out(['id' => $positional[0]]);
        case 'secret:list':
            $out(array_map(fn ($s) => array_merge(State::secret($s['id'], $s['key']), ['notes' => $s['notes'] ?? null]), $w['secrets']));
        case 'secret:create':
            $value = isset($opt['value']) ? $opt['value'] : stream_get_contents(STDIN);
            $id = $next('scrt');
            $w['secrets'][] = ['id' => $id, 'key' => $opt['name'], 'notes' => $opt['notes'] ?? null, 'value_sha' => hash('sha256', (string) $value)];
            $out(['id' => $id, 'key' => $opt['name'], 'notes' => $opt['notes'] ?? null, 'createdAt' => $now, 'updatedAt' => $now]);
        case 'environment-secret:list':
            $e = $w['environments'][$envIndex($positional[0])];
            $out(array_values(array_map(fn ($s) => State::secret($s['id'], $s['key']), array_filter($w['secrets'], fn ($s) => in_array($s['id'], $e['secrets'], true)))));
        case 'domain:list':
            $out(array_values(array_filter($w['domains'], fn ($d) => $d['env'] === $positional[0])));
        case 'command:run':
            // The real CLI prints a status line per change, then the command itself.
            echo json_encode(['command_id' => 'cmd-1', 'status' => 'pending', 'message' => 'Command queued'])."\n";
            echo json_encode(['command_id' => 'cmd-1', 'status' => 'command.running', 'message' => 'Running'])."\n";
            $out(['id' => 'cmd-1', 'command' => $opt['cmd'] ?? '', 'status' => $w['php_status'] ?? 'command.success', 'output' => $w['php_modules'] ?? "Core\nintl\n", 'exitCode' => 0]);
        case 'application:create':
            $id = $next('app');
            $default = $next('env');
            $w['applications'][] = ['id' => $id, 'name' => $opt['name'], 'defaultEnvironmentId' => $default];
            $w['environments'][] = ['id' => $default, 'app' => $id, 'name' => 'main', 'branch' => 'main', 'buildCommand' => null, 'deployCommand' => null, 'databaseSchemaId' => null, 'usesHibernation' => false, 'phpMajorVersion' => '8.4', 'variables' => [], 'secrets' => []];
            $w['instances'][] = ['id' => $next('inst'), 'env' => $default, 'name' => 'App', 'type' => 'app', 'size' => 'flex-512mb', 'usesScheduler' => false, 'maxReplicas' => 1];
            $out(State::app($id, $opt['name'], $w['organisation'], ['defaultEnvironmentId' => $default]));
        case 'environment:create':
            $id = $next('env');
            $w['environments'][] = ['id' => $id, 'pending' => (int) (getenv('CLOUD_STUB_PENDING') ?: 1), 'app' => $positional[0], 'name' => $opt['name'], 'branch' => $opt['branch'], 'buildCommand' => null, 'deployCommand' => null, 'databaseSchemaId' => null, 'usesHibernation' => false, 'phpMajorVersion' => '8.4', 'variables' => [], 'secrets' => []];
            $w['instances'][] = ['id' => $next('inst'), 'env' => $id, 'name' => 'App', 'type' => 'app', 'size' => 'flex-512mb', 'usesScheduler' => false, 'maxReplicas' => 1];
            $out(State::env($id, $opt['name'], $opt['branch'], ['instances' => []]));
        case 'environment:update':
            $i = $envIndex($positional[0]);

            foreach ($w['clusters'] as $c) {
                foreach ($c['schemas'] as $s) {
                    if ($s['id'] === ($opt['database-id'] ?? null) && ($s['pending'] ?? -1) >= 0) {
                        fwrite(STDERR, "stub: schema {$s['id']} is not ready\n");
                        exit(1);
                    }
                }
            }

            foreach (['branch' => 'branch', 'build-command' => 'buildCommand', 'deploy-command' => 'deployCommand', 'database-id' => 'databaseSchemaId'] as $flag => $field) {
                if (isset($opt[$flag])) {
                    $w['environments'][$i][$field] = $opt[$flag];
                }
            }

            if (isset($opt['uses-push-to-deploy'])) {
                $w['environments'][$i]['usesPushToDeploy'] = $opt['uses-push-to-deploy'] === 'true';
            }
            $out($envView($w['environments'][$i], false));
        case 'instance:update':
            foreach ($w['instances'] as &$inst) {
                if ($inst['id'] === $positional[0]) {
                    $inst['size'] = $opt['size'] ?? $inst['size'];
                    $inst['usesScheduler'] = isset($opt['uses-scheduler']) ? $opt['uses-scheduler'] === 'true' : $inst['usesScheduler'];
                    $inst['maxReplicas'] = isset($opt['max-replicas']) ? (int) $opt['max-replicas'] : $inst['maxReplicas'];
                    $inst['minReplicas'] = isset($opt['min-replicas']) ? (int) $opt['min-replicas'] : ($inst['minReplicas'] ?? 1);
                    $inst['hibernationTimeout'] = isset($opt['scale-to-zero-timeout']) ? (int) $opt['scale-to-zero-timeout'] : ($inst['hibernationTimeout'] ?? null);

                    if (isset($opt['scale-to-zero'])) {
                        $w['environments'][$envIndex($inst['env'])]['usesHibernation'] = $opt['scale-to-zero'] === 'true';
                    }
                    $found = $inst;
                }
            }
            unset($inst);
            $out($found ?? ['id' => $positional[0]]);
        case 'database-cluster:create':
            $id = $next('cluster');
            $config = array_filter(['size' => $opt['size'] ?? null, 'storage' => isset($opt['storage']) ? (int) $opt['storage'] : null, 'suspend_seconds' => isset($opt['suspend-seconds']) ? (int) $opt['suspend-seconds'] : null], fn ($v) => $v !== null);
            $w['clusters'][] = ['id' => $id, 'name' => $opt['name'], 'status' => 'creating', 'pending' => (int) (getenv('CLOUD_STUB_PENDING') ?: 1), 'schemas' => ($opt['create-default-database'] ?? 'true') === 'false' ? [] : [['id' => $next('schema'), 'name' => 'main']], 'config' => $config];
            $out(['id' => $id, 'name' => $opt['name'], 'status' => 'creating']);
        case 'database:create':
            $id = $next('schema');
            foreach ($w['clusters'] as &$c) {
                if ($c['id'] === $positional[0] || $c['name'] === $positional[0]) {
                    $c['schemas'][] = ['id' => $id, 'name' => $opt['name'], 'pending' => (int) (getenv('CLOUD_STUB_PENDING') ?: 1)];
                }
            }
            unset($c);
            $out(['id' => $id, 'name' => $opt['name'], 'createdAt' => $now]);
        case 'managed-queue:create':
            $id = $next('queue');
            $w['instances'][] = ['id' => $id, 'env' => $positional[0], 'name' => $opt['name'], 'type' => 'managed_queue', 'size' => $opt['size'], 'usesScheduler' => false, 'maxReplicas' => (int) $opt['max-workers']];
            $out(['id' => $id]);
        case 'environment:variables':
            $i = $envIndex($positional[0]);
            $w['environments'][$i]['variables'][$opt['key']] = $opt['value'];
            $out(['id' => $positional[0]]);
        case 'environment-secret:attach':
            $i = $envIndex($positional[0]);
            $w['environments'][$i]['secrets'] = array_values(array_unique(array_merge($w['environments'][$i]['secrets'], array_slice($positional, 1))));
            $out(['id' => $positional[0]]);
        case 'domain:create':
            $w['domains'][] = ['id' => $next('dom'), 'env' => $positional[0], 'name' => $opt['name']];
            $out(['id' => 'dom']);
        case 'domain:delete':
            $w['domains'] = array_values(array_filter($w['domains'], fn ($d) => $d['id'] !== $positional[0]));
            $out([]);
        case 'environment:delete':
            $i = $envIndex($positional[0]);
            array_splice($w['environments'], $i, 1);
            $w['instances'] = array_values(array_filter($w['instances'], fn ($inst) => $inst['env'] !== $positional[0]));
            $out([]);
        case 'database:delete':
            foreach ($w['clusters'] as &$c) {
                if ($c['id'] === $positional[0]) {
                    $c['schemas'] = array_values(array_filter($c['schemas'], fn ($s) => $s['id'] !== $positional[1]));
                }
            }
            unset($c);
            $out([]);
    }

    fwrite(STDERR, "stub world: no handler for {$command}\n");
    exit(1);
}
