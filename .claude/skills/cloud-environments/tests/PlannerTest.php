<?php

declare(strict_types=1);

use CloudEnvironments\Catalog;
use CloudEnvironments\Cloud;
use CloudEnvironments\CloudError;
use CloudEnvironments\PlanFile;
use CloudEnvironments\PlanRefused;
use CloudEnvironments\Planner;

/**
 * @return array<string, mixed>
 */
function planPreview(State $state, ?array $manifest = null, bool $ownedOnly = true, bool $digestValues = true): array
{
    return (new Planner($state->cloud(), $manifest ?? validManifest(), $ownedOnly, $digestValues))->plan('preview', 'x');
}

/**
 * @param  array<string, mixed>  $plan
 * @return array<string, string>  step key => action
 */
function actions(array $plan): array
{
    return array_column($plan['steps'], 'action', 'key');
}

function organisationOnly(): State
{
    return (new State())->put('application:list', [State::app('app-9', 'other-app')])
        ->put('secret:list', [State::secret('secret-0001', 'SERVICE_PASSWORD'), State::secret('secret-0002', 'SERVICE_PASSWORD')]);
}

test('plan: a different organisation refuses with no further call', function () {
    $state = completePreviewState();
    $state->put('application:list', [State::app('app-1', 'example-app', 'someone-else')]);

    try {
        planPreview($state);
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('someone-else', $e->getMessage());
        assertContains('example-org', $e->getMessage());
    }

    assertSame([['argv' => ['application:list', '--json', '--no-interaction'], 'write' => false]], $state->calls());
});

test('plan control: the matching organisation goes on to read more', function () {
    $state = organisationOnly();

    try {
        planPreview($state);
    } catch (PlanRefused $e) {
        fail('a matching organisation was refused: '.$e->getMessage());
    }

    assertTrue(count($state->calls()) > 1, 'a matching organisation reads on');
});

test('plan: in an empty application every resource is a create step, in order, with ID references', function () {
    $plan = planPreview(organisationOnly());

    assertSame(
        ['application' => 'create', 'default-environment' => 'create', 'cluster' => 'create', 'schema' => 'create', 'environment' => 'create',
            'environment-settings' => 'change', 'instance' => 'change', 'queue' => 'create', 'variable:APP_DEBUG' => 'create', 'variable:APP_ENV' => 'create',
            'variable:MAIL_MAILER' => 'create', 'secret:secret-0001' => 'create'],
        actions($plan),
    );
    assertSame(range(1, 12), array_column($plan['steps'], 'n'));

    $byKey = array_column($plan['steps'], null, 'key');
    assertSame('preview-x', $plan['environment']);
    assertSame('x', $plan['branch']);
    assertSame(['{step:3.id}', '--name=preview-x'], $byKey['schema']['args']);
    assertSame(['{step:1.id}', '--name=preview-x', '--branch=x'], $byKey['environment']['args']);
    assertContains('--database-id={step:4.id}', implode(' ', $byKey['environment-settings']['args']));
    assertSame('{step:5.instances.0}', $byKey['instance']['args'][0]);
    assertSame(['{step:5.id}', '--name=default', '--size=mq.flex.256mb', '--max-workers=3'], $byKey['queue']['args']);
    assertSame(['{step:5.id}', 'secret-0001'], $byKey['secret:secret-0001']['args']);
});

test('plan: a complete environment is all keep, with no command', function () {
    $plan = planPreview(completePreviewState());

    assertSame([], array_values(array_diff(array_values(actions($plan)), ['keep'])));
    assertSame([], array_filter(array_column($plan['steps'], 'command')));
});

test('plan: each differing resource is a change, and a missing one a create', function () {
    $state = completePreviewState();
    $state->put('environment:list@app-1', [State::env('env-1', 'preview-x', 'other-branch', ['databaseSchemaId' => 'schema-1', 'buildCommand' => 'old', 'deployCommand' => validManifest()['deploy_command']])]);
    $state->put('instance:list@env-1', [State::instance('inst-1', 'flex-2gb'), State::instance('queue-1', 'mq.flex.512mb', ['type' => 'queue', 'queueStatus' => 'running', 'maxReplicas' => 3])]);
    $state->put('environment:get@env-1', State::env('env-1', 'preview-x', 'x', ['environmentVariables' => [['key' => 'APP_ENV', 'value' => 'production'], ['key' => 'APP_DEBUG', 'value' => 'true']], 'usesHibernation' => true]));
    $state->put('environment-secret:list@env-1', []);
    $plan = planPreview($state);
    $actions = actions($plan);
    $byKey = array_column($plan['steps'], null, 'key');

    assertSame('change', $actions['environment-settings']);
    assertSame(['env-1', '--branch=x', '--build-command='.validManifest()['build_command'], '--force'], $byKey['environment-settings']['args']);
    assertSame('change', $actions['instance']);
    assertSame('change', $actions['queue']);
    assertSame('change', $actions['variable:APP_ENV']);
    assertSame(['env-1', '--action=set', '--key=APP_ENV', '--value={var:APP_ENV}', '--force'], $byKey['variable:APP_ENV']['args']);
    assertSame('keep', $actions['variable:APP_DEBUG']);
    assertSame('create', $actions['variable:MAIL_MAILER']);
    assertSame('--action=append', $byKey['variable:MAIL_MAILER']['args'][1]);
    assertSame('create', $actions['secret:secret-0001']);
    assertSame('keep', $actions['application']);
});

test('plan: a domain the manifest does not declare is a delete step, a declared one a create', function () {
    $manifest = validManifest();
    $manifest['kinds']['preview']['domains'] = ['preview.example.test'];
    $state = completePreviewState();
    $state->put('domain:list@env-1', [['id' => 'dom-1', 'name' => 'old.example.test']]);
    $actions = actions(planPreview($state, $manifest));

    assertSame('create', $actions['domain:preview.example.test'] ?? null);
    assertSame('delete', $actions['domain:old.example.test'] ?? null);

    $state->put('domain:list@env-1', [['id' => 'dom-2', 'name' => 'preview.example.test']]);
    assertSame('keep', actions(planPreview($state, $manifest))['domain:preview.example.test']);
});

test('plan: a manifest secret that is not in the organisation is refused', function () {
    $state = completePreviewState();
    $state->put('secret:list', []);

    try {
        planPreview($state);
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('secret-0001', $e->getMessage());
    }
});

test('plan: staging and production take their branch from the manifest and no slug', function () {
    $plan = (new Planner(organisationOnly()->cloud(), validManifest()))->plan('production');
    assertSame(['production', 'production'], [$plan['environment'], $plan['branch']]);

    try {
        (new Planner(organisationOnly()->cloud(), validManifest()))->plan('staging', 'x');
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('staging takes no --slug', $e->getMessage());
    }

    $plan = (new Planner(organisationOnly()->cloud(), validManifest()))->plan('preview', 'abc', 'feature/abc');
    assertSame(['preview-abc', 'feature/abc'], [$plan['environment'], $plan['branch']]);
});

test('plan: it makes no write call, and reads each resource kind', function () {
    $state = completePreviewState();
    $state->put('application:create', ['id' => 'app-new']);
    planPreview($state);
    $calls = $state->calls();

    assertTrue($calls !== [], 'the plan read something');
    assertSame([], array_values(array_filter($calls, fn ($c) => $c['write'])));

    $commands = array_unique(array_column(array_column($calls, 'argv'), 0));

    foreach (['application:list', 'environment:list', 'instance:list', 'database-cluster:list', 'secret:list', 'environment-secret:list'] as $read) {
        assertTrue(in_array($read, $commands, true), "{$read} was read");
    }
});

test('plan control: a runner that may write records the create calls it makes, and a read-only runner refuses them', function () {
    $state = new State();
    $state->put('application:create', ['id' => 'app-1']);
    $state->cloud(true)->json(['application:create', '--name=example-app']);

    assertSame([['argv' => ['application:create', '--name=example-app', '--json', '--no-interaction'], 'write' => true]], $state->calls());

    $readOnly = new State();
    $readOnly->put('application:create', ['id' => 'app-1']);

    try {
        $readOnly->cloud(false)->json(['application:create', '--name=example-app']);
        fail('expected a refusal');
    } catch (CloudError $e) {
        assertContains('read-only', $e->getMessage());
    }

    assertSame([], $readOnly->calls());
});

test('plan: every step the planner emits is in the catalog', function () {
    foreach ([planPreview(organisationOnly()), planPreview(completePreviewState())] as $plan) {
        foreach ($plan['steps'] as $step) {
            if ($step['command'] !== null) {
                Catalog::assertEmittable($step['command'], $step['args']);
            }
        }
    }

    try {
        Catalog::assertEmittable('environment:update', ['--no-such-option=1']);
        fail('expected a refusal');
    } catch (InvalidArgumentException $e) {
        assertContains('--no-such-option', $e->getMessage());
    }
});

test('plan: it never names a resource of another application', function () {
    $foreignEnv = State::env('env-foreign', 'preview-x', 'x', ['databaseSchemaId' => 'schema-foreign']);
    $state = completePreviewState();
    $state->put('application:list', [
        State::app('app-1', 'example-app', 'example-org', ['environments' => []]),
        State::app('app-8', 'other-app', 'example-org', ['environments' => [$foreignEnv, State::env('env-foreign-2', 'staging', 'staging')]]),
    ]);
    $state->put('environment:list@app-1', []);
    $state->put('database-cluster:list', [State::cluster('cluster-foreign', 'other-cluster', [['id' => 'schema-foreign', 'name' => 'preview-x', 'createdAt' => 'x']])]);
    $state->put('instance:list@env-foreign', [State::instance('inst-foreign', 'flex-1gb')])->put('environment:get@env-foreign', State::env('env-foreign', 'preview-x', 'x'))
        ->put('environment-secret:list@env-foreign', [])->put('domain:list@env-foreign', []);
    $plan = planPreview($state);
    $text = json_encode($plan);

    foreach (['env-foreign', 'schema-foreign', 'cluster-foreign', 'other-cluster', 'other-app'] as $foreign) {
        assertNotContains($foreign, $text, $foreign);
    }

    assertContains('app-1', $text, 'floor: the owned application is named');
    assertSame('create', actions($plan)['environment']);
});

test('plan control: without the ownership filter another application\'s resources are proposed', function () {
    $foreignEnv = State::env('env-foreign', 'preview-x', 'x', ['databaseSchemaId' => 'schema-foreign']);
    $state = completePreviewState();
    $state->put('application:list', [
        State::app('app-1', 'example-app', 'example-org', ['environments' => []]),
        State::app('app-8', 'other-app', 'example-org', ['environments' => [$foreignEnv]]),
    ]);
    $state->put('environment:list@app-1', []);
    $state->put('database-cluster:list', [State::cluster('cluster-foreign', 'other-cluster', [['id' => 'schema-foreign', 'name' => 'preview-x', 'createdAt' => 'x']])]);

    $state->put('instance:list@env-foreign', [State::instance('inst-foreign', 'flex-1gb')])->put('environment:get@env-foreign', State::env('env-foreign', 'preview-x', 'x'))
        ->put('environment-secret:list@env-foreign', [])->put('domain:list@env-foreign', []);

    assertContains('env-foreign', json_encode(planPreview($state, null, false)));
});

test('plan: a variable value is in neither the plan file nor the output, only its digest', function () {
    $secretLooking = 'value-that-must-not-leak-4471';
    $state = completePreviewState();
    $state->put('environment:get@env-1', State::env('env-1', 'preview-x', 'x', ['environmentVariables' => [['key' => 'APP_ENV', 'value' => $secretLooking]]]));
    $manifest = validManifest();
    $manifest['variables']['APP_NAME'] = 'name-value-5582';

    $plan = planPreview($state, $manifest);
    $json = json_encode($plan);
    assertNotContains($secretLooking, $json);
    assertNotContains('name-value-5582', $json);
    assertContains(hash('sha256', 'name-value-5582'), $json, 'the digest is there');

    $root = tempDir();
    mkdir("{$root}/.git");
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    file_put_contents("{$root}/cloud-environments.json", json_encode($manifest));
    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'plan', 'preview', '--slug=x'], $state->processEnv(), $root);

    assertSame(0, $run['exit'], $run['err']);
    assertNotContains('name-value-5582', $run['out'].$run['err'].file_get_contents("{$root}/.cloud/plans/preview-x.json"));
    assertNotContains($secretLooking, $run['out'].$run['err'].file_get_contents("{$root}/.cloud/plans/preview-x.json"));
});

test('plan control: without the digest step the value is in the plan', function () {
    $state = completePreviewState();
    $manifest = validManifest();
    $manifest['variables']['APP_NAME'] = 'name-value-5582';

    assertContains('name-value-5582', json_encode(planPreview($state, $manifest, true, false)));
});

test('hash: it changes with an action, an argument, the order or the state, and not with a volatile field', function () {
    $base = planPreview(completePreviewState());
    $hash = PlanFile::hash($base);

    assertSame($hash, PlanFile::hash(planPreview(completePreviewState())), 'same manifest and state, same hash');
    assertSame($hash, PlanFile::hash($base + ['hash' => 'stored']), 'the stored hash is not part of it');

    $action = $base;
    $action['steps'][0]['action'] = 'change';
    $arg = $base;
    $arg['steps'][5]['args'][] = '--extra';
    $order = $base;
    [$order['steps'][0], $order['steps'][1]] = [$order['steps'][1], $order['steps'][0]];
    $state = $base;
    $state['state']['environment']['branch'] = 'elsewhere';

    foreach (['action' => $action, 'argument' => $arg, 'order' => $order, 'state' => $state] as $what => $changed) {
        assertTrue(PlanFile::hash($changed) !== $hash, "a changed {$what} changes the hash");
    }

    $volatile = completePreviewState();
    $volatile->put('environment:list@app-1', [State::env('env-1', 'preview-x', 'x', ['databaseSchemaId' => 'schema-1', 'buildCommand' => validManifest()['build_command'], 'deployCommand' => validManifest()['deploy_command'], 'status' => 'deploying', 'statusEnum' => 'deploying', 'updatedAt' => '2026-02-02', 'currentDeploymentId' => 'dep-9', 'deploymentIds' => ['dep-9']])]);
    $volatile->put('instance:list@env-1', [State::instance('inst-1', 'flex-1gb', ['updatedAt' => '2026-02-02', 'hibernationTimeout' => 5]), State::instance('queue-1', 'mq.flex.256mb', ['type' => 'queue', 'queueStatus' => 'paused', 'maxReplicas' => 3])]);

    assertSame($hash, PlanFile::hash(planPreview($volatile)), 'volatile fields do not change the hash');
});

test('plan: a read that fails stops the plan with the error, and one that returns an error body is not an empty list', function () {
    $failing = completePreviewState()->fail('database-cluster:list', 1, 'Unauthenticated.');

    try {
        planPreview($failing);
        fail('expected an error');
    } catch (CloudError $e) {
        assertContains('Unauthenticated', $e->getMessage());
    }

    $errorBody = completePreviewState()->put('environment-secret:list@env-1', ['message' => 'Unauthenticated.']);

    try {
        planPreview($errorBody);
        fail('expected an error');
    } catch (CloudError $e) {
        assertContains('did not return a list', $e->getMessage());
    }

    assertTrue(planPreview(completePreviewState())['steps'] !== [], 'control: the same state with successful reads plans');
});

test('plan: it refuses to write where .cloud/plans is not git-ignored, and writes where it is', function () {
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    $plan = planPreview(completePreviewState());

    try {
        PlanFile::write($root, $plan);
        fail('expected a refusal');
    } catch (RuntimeException $e) {
        assertContains('not git-ignored', $e->getMessage());
    }

    assertTrue(! is_dir("{$root}/.cloud/plans"), 'nothing was written');

    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    $path = PlanFile::write($root, $plan);

    assertSame(PlanFile::hash($plan), PlanFile::read($path)['hash']);
});

test('plan: a manifest with no database, instance, queue, secrets or commands plans only what it declares', function () {
    $manifest = validManifest();
    unset($manifest['kinds']['preview']['database'], $manifest['instance'], $manifest['queue'], $manifest['secrets'], $manifest['build_command'], $manifest['deploy_command']);
    $plan = planPreview(organisationOnly(), $manifest);

    assertSame(['application', 'default-environment', 'environment', 'environment-settings', 'variable:APP_DEBUG', 'variable:APP_ENV', 'variable:MAIL_MAILER'], array_column($plan['steps'], 'key'));
    assertSame(['{step:3.id}', '--branch=x', '--force'], array_column($plan['steps'], null, 'key')['environment-settings']['args']);
});

test('plan: the instance step refers to the environment step wherever it falls', function () {
    $manifest = validManifest();
    unset($manifest['kinds']['preview']['database']);
    $steps = array_column(planPreview(organisationOnly(), $manifest)['steps'], null, 'key');

    assertSame('{step:3.instances.0}', $steps['instance']['args'][0]);
});

test('plan: plan files are named by environment and carry no absolute path', function () {
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    $path = PlanFile::write($root, planPreview(completePreviewState()));

    assertSame("{$root}/.cloud/plans/preview-x.json", $path);
    assertNotContains($root, (string) file_get_contents($path));
});

test('cli: plan exits 1 with a message for an unknown kind, a missing manifest, an invalid manifest and a read that fails', function () {
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    $state = organisationOnly();
    $env = $state->processEnv();
    $cli = ['php', skillDir().'/scripts/cloud-env.php', 'plan'];

    $missing = runProcess([...$cli, 'preview', '--slug=x'], $env, $root);
    assertSame(1, $missing['exit']);
    assertContains('manifest not found', $missing['err']);

    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $unknown = runProcess([...$cli, 'qa'], $env, $root);
    assertSame(1, $unknown['exit']);
    assertContains('no kind qa', $unknown['err']);

    $noSlug = runProcess([...$cli, 'preview'], $env, $root);
    assertSame(1, $noSlug['exit']);
    assertContains('--slug', $noSlug['err']);

    $invalid = validManifest();
    unset($invalid['region']);
    file_put_contents("{$root}/cloud-environments.json", json_encode($invalid));
    $bad = runProcess([...$cli, 'preview', '--slug=x'], $env, $root);
    assertSame(1, $bad['exit']);
    assertContains('region: missing', $bad['err']);

    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $state->fail('database-cluster:list', 1, 'Unauthenticated.');
    $failed = runProcess([...$cli, 'preview', '--slug=x'], $state->processEnv(), $root);
    assertSame(1, $failed['exit']);
    assertContains('Unauthenticated', $failed['err']);
    assertTrue(! is_file("{$root}/.cloud/plans/preview-x.json"), 'no plan was written');

    $ok = runProcess([...$cli, 'preview', '--slug=x'], organisationOnly()->processEnv(), $root);
    assertSame(0, $ok['exit'], $ok['err']);
    assertContains('hash: ', $ok['out']);
});

test('plan: a schema already used by another environment is refused, and one used by this environment is kept', function () {
    $state = completePreviewState();
    $state->put('application:list', [
        State::app('app-1', 'example-app', 'example-org'),
        State::app('app-8', 'other-app', 'example-org'),
    ]);
    $state->put('environment:list@app-8', [State::env('env-foreign', 'preview-x', 'x', ['databaseSchemaId' => 'schema-1'])]);

    try {
        planPreview($state);
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('schema-1', $e->getMessage());
        assertContains('env-foreign', $e->getMessage());
    }

    assertSame('keep', actions(planPreview(completePreviewState()))['schema']);
});

test('plan: an existing environment with no app instance is refused', function () {
    $state = completePreviewState();
    $state->put('instance:list@env-1', [State::instance('queue-1', 'mq.flex.256mb', ['type' => 'queue', 'queueStatus' => 'running', 'maxReplicas' => 3])]);

    try {
        planPreview($state);
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('no app instance', $e->getMessage());
    }
});

test('plan: variables are read with --show-sensitive, and nothing else is', function () {
    $state = completePreviewState();
    planPreview($state);

    foreach ($state->calls() as $call) {
        $sensitive = in_array('--show-sensitive', $call['argv'], true);
        assertSame($call['argv'][0] === 'environment:get', $sensitive, $call['argv'][0]);
    }
});

test('plan: a differing instance timeout is a change, and the timeout is written with it', function () {
    $manifest = validManifest();
    $manifest['instance']['sleep_timeout'] = 99;
    $steps = array_column(planPreview(completePreviewState(), $manifest)['steps'], null, 'key');

    assertSame('change', $steps['instance']['action']);
    assertContains('--scale-to-zero-timeout=99', implode(' ', $steps['instance']['args']));
    assertSame('keep', array_column(planPreview(completePreviewState())['steps'], null, 'key')['instance']['action'], 'control: the read-back timeout of 5 matches');
});

test('cloud: a failed read of variable values does not repeat the CLI output, and other failures do', function () {
    $state = completePreviewState()->fail('environment:get', 1, 'leaked-value-9931');

    try {
        planPreview($state);
        fail('expected an error');
    } catch (CloudError $e) {
        assertNotContains('leaked-value-9931', $e->getMessage());
        assertContains('environment:get failed', $e->getMessage());
    }

    try {
        planPreview(completePreviewState()->put('environment:get@env-1', 'not json leaked-value-9931'));
        fail('expected an error');
    } catch (CloudError $e) {
        assertNotContains('leaked-value-9931', $e->getMessage());
        assertContains('not JSON', $e->getMessage());
    }

    try {
        planPreview(completePreviewState()->put('secret:list', 'not json visible-reason-2'));
        fail('expected an error');
    } catch (CloudError $e) {
        assertContains('visible-reason-2', $e->getMessage());
    }

    try {
        planPreview(completePreviewState()->fail('secret:list', 1, 'visible-reason-1'));
        fail('expected an error');
    } catch (CloudError $e) {
        assertContains('visible-reason-1', $e->getMessage());
    }
});

test('plan: the schema check reads each application\'s environments, since application:list embeds none with a schema', function () {
    $state = completePreviewState();
    $state->put('application:list', [
        State::app('app-1', 'example-app', 'example-org', ['defaultEnvironmentId' => 'env-0']),
        State::app('app-8', 'other-app', 'example-org', ['environments' => [State::env('env-foreign', 'preview-x', 'x')]]),
    ]);
    $state->put('environment:list@app-8', [State::env('env-foreign', 'preview-x', 'x', ['databaseSchemaId' => 'schema-1'])]);

    try {
        planPreview($state);
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('env-foreign', $e->getMessage());
    }

    assertTrue(in_array(['environment:list', 'app-8', '--json', '--no-interaction'], array_column($state->calls(), 'argv'), true), 'the other application\'s environments were listed');
});

test('plan: a kind\'s own build and deploy commands replace the top-level ones for that kind only', function () {
    $manifest = validManifest();
    $manifest['kinds']['preview']['deploy_command'] = 'php artisan migrate --force && php artisan db:seed --force';
    $byKey = array_column(planPreview(completePreviewState(), $manifest)['steps'], null, 'key');

    assertSame('change', $byKey['environment-settings']['action']);
    assertSame(['env-1', '--deploy-command=php artisan migrate --force && php artisan db:seed --force', '--force'], $byKey['environment-settings']['args']);

    // Control: an override on another kind leaves the preview at the top-level commands.
    $control = validManifest();
    $control['kinds']['staging']['deploy_command'] = 'php artisan migrate --force && php artisan db:seed --force';
    $control['kinds']['staging']['build_command'] = 'npm ci';

    assertSame('keep', actions(planPreview(completePreviewState(), $control))['environment-settings']);
});
