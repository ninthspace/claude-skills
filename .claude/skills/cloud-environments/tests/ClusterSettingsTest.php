<?php

declare(strict_types=1);

use CloudEnvironments\ApplyRefused;
use CloudEnvironments\ManifestValidator;
use CloudEnvironments\Planner;
use CloudEnvironments\Verifier;

/**
 * @return array<string, mixed>
 */
function manifestWithClusterSettings(): array
{
    $m = validManifest();
    $m['databases']['non_production'] += ['size' => 'mysql-flex-512mb', 'storage' => 5, 'scale_to_zero_after' => 300];

    return $m;
}

test('cluster settings: a new cluster is created with its settings in one call, and a later plan keeps them', function () {
    $world = new World();
    $m = manifestWithClusterSettings();
    $plan = worldPlan($world, $m);
    $steps = array_column($plan['steps'], null, 'key');

    assertSame(['--name=example-nonprod', '--type=laravel_mysql', '--region=eu-west-2', '--size=mysql-flex-512mb', '--storage=5', '--suspend-seconds=300', '--create-default-database=false'], $steps['cluster']['args']);
    assertTrue(! isset($steps['cluster-settings']), 'no separate settings step for a new cluster');

    applyOrFail(applier($world, $m), $plan);
    $config = $world->read()['clusters'][1]['config'];
    assertSame(['size' => 'mysql-flex-512mb', 'storage' => 5, 'suspend_seconds' => 300], $config);

    assertSame('keep', worldActions($world, $m)['cluster-settings'], 'control: the read-back size matches the declared one');
});

test('cluster settings: a differing setting is a change, and a manifest without settings has no step', function () {
    $world = new World();
    $m = manifestWithClusterSettings();
    $plan = worldPlan($world, $m);
    applyOrFail(applier($world, $m), $plan);

    foreach (['size' => 'mysql-flex-2gb', 'storage' => 20, 'suspend_seconds' => 0] as $field => $value) {
        $copy = new World();
        $copy->write($world->read());
        $copy->change(function (array &$w) use ($field, $value) { $w['clusters'][1]['config'][$field] = $value; });

        assertSame('change', worldActions($copy, $m)['cluster-settings'], $field);
    }

    assertTrue(! array_key_exists('cluster-settings', worldActions($world)), 'no settings, no step');
    assertSame('flex-512mb', Planner::sizeKey('db-flex.m-1vcpu-512mb'));
    assertSame('flex-512mb', Planner::sizeKey('mysql-flex-512mb'));
    assertSame('pro-16gb', Planner::sizeKey('db-pro.m-4vcpu-16gb'));
});

test('cluster settings: a change after the plan refuses apply, and verify names each difference', function () {
    $world = new World();
    $m = manifestWithClusterSettings();
    applyOrFail(applier($world, $m), worldPlan($world, $m));
    changePreview($world, function (array &$e) { $e['phpMajorVersion'] = '8.5'; });
    $verifier = new Verifier($world->cloud(), $m, $world->cloud(true));

    assertSame([], $verifier->verify('preview-x'));

    $m2 = $m;
    $m2['variables']['APP_NAME'] = 'x';
    $plan = worldPlan($world, $m2);
    $world->change(function (array &$w) { $w['clusters'][1]['config']['storage'] = 20; });

    try {
        applier($world, $m2)->apply($plan, $plan['hash']);
        fail('expected a refusal');
    } catch (ApplyRefused $e) {
        assertContains('Cloud has changed since the plan was made (cluster)', $e->getMessage());
    }

    $world->change(function (array &$w) {
        $w['clusters'][1]['config'] = ['size' => 'mysql-flex-2gb', 'storage' => 20, 'suspend_seconds' => 0];
    });
    $failures = implode("\n", $verifier->verify('preview-x'));

    assertContains('database size: mysql-flex-2gb, expected mysql-flex-512mb', $failures);
    assertContains('database storage: 20 GB, expected 5', $failures);
    assertContains('database scale to zero: 0 seconds, expected 300', $failures);
});

test('cluster settings: the validator refuses settings of the wrong type', function () {
    foreach (['size' => 512, 'storage' => '5', 'scale_to_zero_after' => '300'] as $field => $value) {
        $m = validManifest();
        $m['databases']['non_production'][$field] = $value;

        assertErrorNames("databases.non_production.{$field}", ManifestValidator::validate($m), $field);
    }

    assertSame([], ManifestValidator::validate(manifestWithClusterSettings()), 'control: the right types pass');
});

test('cluster settings: without settings a new cluster is created with name, type and region only, and an existing one gets the settings step', function () {
    $world = new World();
    $plan = worldPlan($world);
    $steps = array_column($plan['steps'], null, 'key');

    assertSame(['--name=example-nonprod', '--type=laravel_mysql', '--region=eu-west-2', '--create-default-database=false'], $steps['cluster']['args']);

    applyOrFail(applier($world), $plan);
    $m = manifestWithClusterSettings();
    $later = array_column(worldPlan($world, $m)['steps'], null, 'key');

    assertSame('change', $later['cluster-settings']['action'] ?? null);
    assertSame('database-cluster:update', $later['cluster-settings']['command']);
    assertSame([], $world->read()['clusters'][1]['schemas'] === [] ? [] : array_column(array_filter($world->read()['clusters'][1]['schemas'], fn ($s) => $s['name'] === 'main'), 'name'), 'no default database');
});

test('cluster settings: storage below 5 GB and scale to zero outside 0-3600 seconds are refused', function () {
    foreach (['storage' => 4, 'scale_to_zero_after' => 3601] as $field => $value) {
        $m = manifestWithClusterSettings();
        $m['databases']['non_production'][$field] = $value;
        assertErrorNames("databases.non_production.{$field}", ManifestValidator::validate($m), $field);
    }

    $m = manifestWithClusterSettings();
    $m['databases']['non_production']['scale_to_zero_after'] = -1;
    assertErrorNames('databases.non_production.scale_to_zero_after', ManifestValidator::validate($m));

    $m['databases']['non_production']['scale_to_zero_after'] = 0;
    assertSame([], ManifestValidator::validate($m), 'control: 0 (never) passes');
});
