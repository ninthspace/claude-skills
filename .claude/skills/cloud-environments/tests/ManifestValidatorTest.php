<?php

declare(strict_types=1);

use CloudEnvironments\InjectedKeys;
use CloudEnvironments\Manifest;
use CloudEnvironments\ManifestValidator;

/**
 * @param  array<string, mixed>  $m
 * @return array<string, mixed>
 */
function without(array $m, string $path): array
{
    $parts = explode('.', $path);
    $last = array_pop($parts);
    $ref = &$m;

    foreach ($parts as $part) {
        $ref = &$ref[$part];
    }

    unset($ref[$last]);

    return $m;
}

test('validator: the valid fixture passes', function () {
    assertSame([], ManifestValidator::validate(validManifest()));
});

test('validator: a missing organisation, application, repository or region is refused by name', function () {
    foreach (['organisation', 'application', 'repository', 'region'] as $field) {
        assertErrorNames($field, ManifestValidator::validate(without(validManifest(), $field)), $field);
        $blank = validManifest();
        $blank[$field] = '  ';
        assertErrorNames($field, ManifestValidator::validate($blank), "blank {$field}");
    }
});

test('validator: a kind without a branch rule is refused, a fixed rule needs a name', function () {
    foreach (Manifest::KINDS as $kind) {
        assertTrue(in_array("kinds.{$kind}.branch: missing", ManifestValidator::validate(without(validManifest(), "kinds.{$kind}.branch")), true), "{$kind} without a branch rule");
    }

    $m = validManifest();
    $m['kinds']['staging']['branch'] = ['rule' => 'fixed'];
    assertErrorNames('kinds.staging.branch.name', ManifestValidator::validate($m));

    $m['kinds']['staging']['branch'] = ['rule' => 'sometimes'];
    assertErrorNames('kinds.staging.branch.rule', ManifestValidator::validate($m));
});

test('validator: a secret without allowed kinds, or with an unknown one, is refused', function () {
    assertErrorNames('secrets[0].kinds', ManifestValidator::validate(without(validManifest(), 'secrets.0.kinds')));

    $m = validManifest();
    $m['secrets'][0]['kinds'] = [];
    assertErrorNames('secrets[0].kinds', ManifestValidator::validate($m));

    $m['secrets'][0]['kinds'] = ['qa'];
    assertErrorNames('secrets[0].kinds', ManifestValidator::validate($m));

    $m = validManifest();
    $m['secrets'][1]['class'] = 'both';
    assertErrorNames('secrets[1].class', ManifestValidator::validate($m));

    $m = validManifest();
    $m['secrets'][1]['id'] = '';
    assertErrorNames('secrets[1].id', ManifestValidator::validate($m));

    $m = validManifest();
    unset($m['secrets'][1]['id']);
    assertSame([], ManifestValidator::validate($m), 'a missing ID is allowed until `secrets` writes it');
});

test('validator: an unknown environment kind is refused', function () {
    $m = validManifest();
    $m['kinds']['qa'] = ['branch' => ['rule' => 'slug']];

    assertErrorNames('kinds.qa', ManifestValidator::validate($m));
});

test('validator: every injected or managed key is refused as a variable', function () {
    $table = InjectedKeys::table();
    $keys = $table['exact'];

    foreach ($table['prefixes'] as $prefix) {
        $keys[] = $prefix.'EXAMPLE';
    }

    assertTrue(count($keys) >= 12, 'the table lists the exact keys and the AWS_ prefix');

    foreach ($keys as $key) {
        $m = validManifest();
        $m['variables'][$key] = 'x';
        assertErrorNames("variables.{$key}", ManifestValidator::validate($m), $key);
    }
});

test('validator: an injected key is refused in per-kind variables, required values and secret keys', function () {
    $m = validManifest();
    $m['kinds']['production']['variables']['DB_HOST'] = 'x';
    assertErrorNames('kinds.production.variables.DB_HOST', ManifestValidator::validate($m));

    $m = validManifest();
    $m['kinds']['preview']['required']['APP_KEY'] = ['equals' => 'x'];
    assertErrorNames('kinds.preview.required.APP_KEY', ManifestValidator::validate($m));

    $m = validManifest();
    $m['secrets'][0]['key'] = 'AWS_SECRET_ACCESS_KEY';
    assertErrorNames('secrets[0].key', ManifestValidator::validate($m));
});

test('validator control: the keys outside the table pass', function () {
    $m = validManifest();
    $m['variables']['AWSOME_FLAG'] = 'x';
    $m['variables']['APP_NAME'] = 'x';

    assertSame([], ManifestValidator::validate($m));
});

test('manifest: a kind value overrides the default, and a default applies where a kind has none', function () {
    $m = validManifest();

    assertSame('production', Manifest::variablesFor($m, 'production')['APP_ENV']);
    assertSame('local', Manifest::variablesFor($m, 'preview')['APP_ENV']);
    assertSame('log', Manifest::variablesFor($m, 'staging')['MAIL_MAILER']);
    assertSame('smtp', Manifest::variablesFor($m, 'production')['MAIL_MAILER']);
});

test('validator: a required value the kind does not meet is refused, and met ones pass', function () {
    $m = validManifest();
    $m['kinds']['preview']['variables']['MAIL_MAILER'] = 'smtp';
    assertErrorNames('kinds.preview.required.MAIL_MAILER', ManifestValidator::validate($m));

    $m = validManifest();
    $m['kinds']['production']['variables']['MAIL_MAILER'] = 'log';
    assertErrorNames('kinds.production.required.MAIL_MAILER', ManifestValidator::validate($m));

    $m = validManifest();
    unset($m['kinds']['production']['variables']['APP_DEBUG']);
    $m['variables']['APP_DEBUG'] = 'true';
    assertErrorNames('kinds.production.required.APP_DEBUG', ManifestValidator::validate($m));

    assertSame([], ManifestValidator::validate(validManifest()));
});

test('cli: validate exits 1 naming the field for a bad manifest and 0 for a good one', function () {
    $dir = tempDir();
    file_put_contents("{$dir}/bad.json", json_encode(without(validManifest(), 'region')));

    $bad = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'validate', "{$dir}/bad.json"]);
    assertSame(1, $bad['exit']);
    assertContains('region: missing', $bad['err']);

    $good = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'validate', skillDir().'/tests/fixtures/manifest-valid.json']);
    assertSame(0, $good['exit']);
    assertSame(0, runProcess(['php', skillDir().'/scripts/cloud-env.php', 'validate', '--manifest='.skillDir().'/tests/fixtures/manifest-valid.json'])['exit'], 'validate takes --manifest= too');
});

test('validator: unexpected shapes are refused with a message, never an exception', function () {
    $cases = [
        'kinds.preview' => 'text',
        'kinds.production.required' => 'text',
        'kinds.production.required.APP_ENV' => 'text',
        'kinds.production.variables' => 'text',
        'kinds.production.variables.APP_ENV' => 42,
        'variables' => null,
        'variables.APP_NAME' => ['x'],
        'secrets' => 'text',
        'secrets.0' => 'text',
        'secrets.0.kinds' => 'preview',
        'kinds' => [],
        'organisation' => 7,
        'kinds.preview.database' => ['x'],
        'kinds.production.required.MAIL_MAILER.not' => ['log'],
        'kinds.preview.required.MAIL_MAILER.equals' => ['log'],
        'secrets.0.key' => ['x'],
        'secrets.0.id' => ['x'],
    ];

    foreach ($cases as $path => $value) {
        $m = validManifest();
        $parts = explode('.', $path);
        $ref = &$m;

        foreach ($parts as $part) {
            $ref = &$ref[$part];
        }

        $ref = $value;
        unset($ref);

        $errors = ManifestValidator::validate($m);
        assertTrue($errors !== [], "{$path} set to ".json_encode($value).' is refused');
        assertNotContains('Array', implode("\n", $errors), "{$path} message");
    }
});

test('validator: secrets given as an object are refused', function () {
    $m = validManifest();
    $m['secrets'] = ['a' => $m['secrets'][0]];

    assertErrorNames('secrets', ManifestValidator::validate($m));
});

test('cli: a non-string database exits 1 with a message, not a fatal error', function () {
    $dir = tempDir();
    $m = validManifest();
    $m['kinds']['preview']['database'] = ['x'];
    file_put_contents("{$dir}/m.json", json_encode($m));

    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'validate', "{$dir}/m.json"]);

    assertSame(1, $run['exit']);
    assertContains('kinds.preview.database', $run['err']);
    assertNotContains('Fatal error', $run['err'].$run['out']);
});

test('validator: a kind naming a database that is not declared is refused', function () {
    $m = validManifest();
    $m['kinds']['preview']['database'] = 'nonexistent';

    assertErrorNames('kinds.preview.database', ManifestValidator::validate($m));
});

test('validator: the sections the planner reads are checked for presence and type', function () {
    $cases = [
        'kinds.staging.branch' => ['rule' => 'slug'],
        'kinds.production.branch' => ['rule' => 'slug'],
        'instance' => ['scheduler' => true],
        'instance.size' => '',
        'instance.scheduler' => 'yes',
        'instance.sleep_timeout' => '5',
        'queue' => ['size' => 'mq.flex.256mb'],
        'queue.max_workers' => '3',
        'databases.production.name' => ['x'],
        'databases.production' => ['name' => 'p'],
        'databases' => 'text',
        'kinds.preview.domains' => 'a.example.test',
        'kinds.preview.domains.0' => ['x'],
        'build_command' => '',
        'deploy_command' => 5,
    ];

    foreach ($cases as $path => $value) {
        $m = validManifest();
        $parts = explode('.', $path);
        $ref = &$m;

        foreach ($parts as $part) {
            $ref = &$ref[$part];
        }

        $ref = $value;
        unset($ref);
        $errors = ManifestValidator::validate($m);

        assertErrorNames(preg_replace('/\.\d+$/', '', $path), $errors, "{$path} set to ".json_encode($value));
        assertNotContains('Array', implode("\n", $errors), "{$path} message");
    }

    $m = validManifest();
    $m['kinds']['preview']['domains'] = ['a' => 'a.example.test'];
    assertErrorNames('kinds.preview.domains', ManifestValidator::validate($m), 'domains given as an object');

    $m = validManifest();
    $m['kinds']['preview']['domains'] = ['a.example.test'];
    assertSame([], ManifestValidator::validate($m), 'control: a list of domains passes');
});

test('validator: a secret whose class does not fit its kinds, a key attached twice to one kind, or a key also declared as a variable is refused', function () {
    $m = validManifest();
    $m['secrets'][1]['kinds'] = ['preview', 'production'];
    assertErrorNames('secrets[1].kinds', ManifestValidator::validate($m), 'live on a preview');

    $m = validManifest();
    $m['secrets'][1]['kinds'] = ['staging', 'production'];
    assertErrorNames('secrets[1].kinds', ManifestValidator::validate($m), 'live on staging');

    $m = validManifest();
    $m['secrets'][0]['kinds'] = ['staging', 'production'];
    assertErrorNames('secrets[0].kinds', ManifestValidator::validate($m), 'test on production');

    $m = validManifest();
    $m['secrets'][] = ['key' => 'SERVICE_PASSWORD', 'id' => 'secret-0003', 'class' => 'test', 'kinds' => ['staging']];
    assertErrorNames('secrets[2].key', ManifestValidator::validate($m), 'the same key twice on staging');

    $m = validManifest();
    $m['kinds']['production']['variables']['SERVICE_PASSWORD'] = 'plain';
    assertErrorNames('secrets[0].key', ManifestValidator::validate($m), 'a secret key declared as a variable');

    assertSame([], ManifestValidator::validate(validManifest()), 'control: a test and a live secret sharing a key on different kinds pass');
});

test('validator: the php section needs a major.minor version and a list of extension names', function () {
    foreach ([['version' => ['8.5']], ['version' => '8'], ['version' => '8.5', 'extensions' => 'intl'], ['version' => '8.5', 'extensions' => [['intl']]], ['version' => '8.5', 'extensions' => ['']], 'text'] as $php) {
        $m = validManifest();
        $m['php'] = $php;
        $errors = ManifestValidator::validate($m);

        assertTrue($errors !== [] && str_starts_with($errors[0], 'php.'), json_encode($php).' is refused: '.json_encode($errors));
    }

    assertSame([], ManifestValidator::validate(validManifest()), 'control: the example php section passes');
});

test('validator: scale to zero needs its timeout, since the CLI will not turn it on without one', function () {
    $m = validManifest();
    unset($m['instance']['sleep_timeout']);
    assertErrorNames('instance.sleep_timeout', ManifestValidator::validate($m));

    $m['instance']['scale_to_zero'] = false;
    assertSame([], ManifestValidator::validate($m), 'control: without scale to zero no timeout is needed');

    $m = validManifest();
    $m['instance']['sleep_timeout'] = 0;
    assertErrorNames('instance.sleep_timeout', ManifestValidator::validate($m), 'a timeout of 0');
});

test('validator: push_to_deploy must be true or false', function () {
    $m = validManifest();
    $m['kinds']['staging']['push_to_deploy'] = 'yes';
    assertErrorNames('kinds.staging.push_to_deploy', ManifestValidator::validate($m));

    $m['kinds']['staging']['push_to_deploy'] = true;
    assertSame([], ManifestValidator::validate($m), 'control: a boolean passes');
});

test('validator: min_workers must be 0 up to max_workers', function () {
    foreach ([-1, 4, '0'] as $value) {
        $m = validManifest();
        $m['queue']['min_workers'] = $value;
        assertErrorNames('queue.min_workers', ManifestValidator::validate($m), json_encode($value));
    }

    $m = validManifest();
    $m['queue']['min_workers'] = 0;
    assertSame([], ManifestValidator::validate($m), 'control: 0 passes');
});
