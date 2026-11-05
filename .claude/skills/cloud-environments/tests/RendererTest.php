<?php

declare(strict_types=1);

use CloudEnvironments\PlanFile;
use CloudEnvironments\Planner;
use CloudEnvironments\Renderer;

/**
 * @return array<string, mixed>
 */
function rates(): array
{
    return json_decode((string) file_get_contents(skillDir().'/references/rates.json'), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * A preview, staging and production plan, as `plan` makes them for an organisation that
 * holds nothing of the application yet.
 *
 * @return list<array<string, mixed>>
 */
function threePlans(): array
{
    $manifest = validManifest();
    $manifest['variables']['APP_NAME'] = 'value-xyz-7731';

    return [
        (new Planner(organisationOnly()->cloud(), $manifest))->plan('preview', 'x'),
        (new Planner(organisationOnly()->cloud(), $manifest))->plan('staging'),
        (new Planner(organisationOnly()->cloud(), $manifest))->plan('production'),
    ];
}

test('render: every environment shows its steps, branch, variable names, secrets with allowed kinds, and cost', function () {
    $plans = threePlans();
    $html = Renderer::render($plans, rates());

    foreach ($plans as $plan) {
        $env = $plan['environment'];
        assertContains('data-environment="'.$env.'" data-kind="'.$plan['kind'].'"', $html);
        assertContains('branch '.$plan['branch'], $html);

        foreach ($plan['steps'] as $step) {
            assertContains('data-step="'.$step['n'].'" data-action="'.$step['action'].'"', $html, "{$env} step {$step['n']}");
        }

        assertContains('data-variable="APP_NAME"', $html);
    }

    assertSame(array_sum(array_map(fn ($p) => count($p['steps']), $plans)), substr_count($html, 'data-step='), 'one row per step');
});

test('render: the secrets band has one element per attachment, with its environment and allowed kinds', function () {
    $html = Renderer::render(threePlans(), rates());

    assertContains('data-secret="secret-0001" data-environment="preview-x"', $html);
    assertContains('data-secret="secret-0001" data-environment="staging"', $html);
    assertContains('data-secret="secret-0002" data-environment="production"', $html);
    assertSame(3, substr_count($html, 'data-secret='));
    assertContains('test · allowed on preview, staging', $html);
    assertContains('live · allowed on production', $html);
});

test('render: no variable value or digest is on the page', function () {
    $html = Renderer::render(threePlans(), rates());

    assertNotContains('value-xyz-7731', $html);
    assertNotContains(hash('sha256', 'value-xyz-7731'), $html);
});

test('render: the estimated cost comes from the rates table, marks estimated lines, and follows a changed rate', function () {
    $plans = threePlans();
    $html = Renderer::render($plans, rates());

    assertContains('App instance flex-1gb', $html);
    assertContains('data-cost-cents="1400">$14.00</td>', $html);
    assertContains('data-cost-cents="430">$4.30 <span class="est">estimate</span>', $html);
    assertContains('Database cluster example-nonprod (shared; counted once)', $html);
    assertSame(1, substr_count($html, 'Database cluster example-nonprod'), 'a shared cluster is counted once on the page');
    assertContains('Rates read 2026-09-11', $html);

    $changed = rates();
    $changed['lines']['instance']['flex-1gb']['monthly_cents'] = 9900;
    $other = Renderer::render($plans, $changed);

    assertContains('data-cost-cents="9900">$99.00</td>', $other);
    assertNotContains('data-cost-cents="1400">', $other);
});

test('render: an attachment outside the secret\'s allowed kinds is a warning at the top, and clean plans have none', function () {
    $clean = Renderer::render(threePlans(), rates());
    assertNotContains('class="warnings"', $clean);
    assertNotContains('data-warning', $clean);
    assertNotContains('secret warn', $clean);

    $plans = threePlans();
    $plans[0]['state']['secrets'] = ['secret-0002'];
    $plans[2]['state']['secrets'] = ['secret-0001'];
    $html = Renderer::render($plans, rates());

    assertContains('class="warnings"', $html);
    assertSame(2, substr_count($html, 'data-warning'));
    assertTrue(strpos($html, 'class="warnings"') < strpos($html, 'data-environment="preview-x" data-kind'), 'the warnings come first');
    assertContains('SERVICE_PASSWORD (live, secret-0002) is not allowed on a preview', $html);
    assertContains('SERVICE_PASSWORD (test, secret-0001) is not allowed on a production', $html);
    assertContains('class="secret warn" data-secret="secret-0002" data-environment="preview-x"', $html);
});

test('render: a secret absent from the plan\'s catalog, or of the wrong class for the kind, is a warning', function () {
    $plans = threePlans();
    $plans[1]['state']['secrets'] = ['secret-9999'];
    assertContains('secret secret-9999 is attached to staging but is not in the manifest', Renderer::render($plans, rates()));

    $plans = threePlans();
    $plans[1]['secrets'][1]['kinds'][] = 'staging';
    $plans[1]['state']['secrets'] = ['secret-0002'];
    assertContains('is a live secret and staging is a staging', Renderer::render($plans, rates()));
});

test('render: it reads only its plan files and makes no Cloud call', function () {
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $state = organisationOnly();
    $env = $state->processEnv();
    $planned = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'plan', 'preview', '--slug=x'], $env, $root);

    assertSame(0, $planned['exit'], $planned['err']);
    assertTrue(count($state->calls()) > 0, 'control: the recorder sees the calls a plan makes');

    unlink($state->dir.'/calls.log');
    $out = "{$root}/diagram.html";
    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', "{$root}/.cloud/plans/preview-x.json", "--out={$out}"], $env, $root);

    assertSame(0, $run['exit'], $run['err']);
    assertSame([], $state->calls());
    assertContains('data-environment="preview-x"', (string) file_get_contents($out));
});

test('render: the page meets the artifact contract', function () {
    $html = Renderer::render(threePlans(), rates());

    assertTrue(preg_match('/<title>([^<]+)<\/title>/', $html, $m) === 1 && str_word_count($m[1]) >= 2 && str_word_count($m[1]) <= 4, 'a two-to-four-word title');
    assertContains(':root { --bg:', $html);
    assertContains('@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --bg:', $html);
    assertContains(':root[data-theme="dark"] { --bg:', $html);
    assertTrue(preg_match('/body \{[^}]*background: var\(--bg\)/', $html) === 1, 'an explicit body background');
    assertSame(0, preg_match('/<script/i', $html), 'no script');
    assertSame(0, preg_match('/<link/i', $html), 'no stylesheet link');
    assertSame(0, preg_match('/(src|href)\s*=\s*["\']?(https?:)?\/\//i', $html), 'no external reference');
    assertSame(0, preg_match('/<(!doctype|html|head|body)[\s>]/i', $html), 'no document skeleton of its own');
    assertContains('padding-left: max(16px, env(safe-area-inset-left, 0px))', $html);
});

test('render: text in a plan is escaped, and a plan without resources still renders', function () {
    $plans = threePlans();
    $plans[0]['steps'][0]['note'] = '<script>alert(1)</script>';
    unset($plans[1]['resources']);
    $html = Renderer::render($plans, rates());

    assertNotContains('<script>alert', $html);
    assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    assertContains('data-environment="staging" data-kind="staging"', $html);
});

test('cli: render exits 1 for a file that is not a plan and 2 without one', function () {
    $dir = tempDir();
    file_put_contents("{$dir}/bad.json", 'not json');

    assertSame(1, runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', "{$dir}/bad.json"])['exit']);
    assertSame(1, runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', "{$dir}/missing.json"])['exit']);
    assertSame(2, runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render'])['exit']);
});

test('cli: render exits 1 for JSON that is not a plan, and for an output it cannot write', function () {
    $dir = tempDir();
    file_put_contents("{$dir}/object.json", '{}');
    file_put_contents("{$dir}/manifest.json", json_encode(validManifest()));

    foreach (['object.json', 'manifest.json'] as $file) {
        $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', "{$dir}/{$file}"]);
        assertSame(1, $run['exit'], $file);
        assertContains('not a plan file', $run['err']);
        assertNotContains('Warning', $run['out'].$run['err']);
    }

    $plan = threePlans()[1];
    $plan['hash'] = PlanFile::hash($plan);
    file_put_contents("{$dir}/staging.json", json_encode($plan));
    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', "{$dir}/staging.json", "--out={$dir}/missing/x.html"]);

    assertSame(1, $run['exit']);
    assertContains('could not write', $run['err']);
    assertNotContains('diagram:', $run['out']);
});

test('render: a total with an unpriced line is marked incomplete, and each section shows its plan hash', function () {
    $plans = threePlans();
    $plans[0]['hash'] = PlanFile::hash($plans[0]);
    assertNotContains('data-incomplete', Renderer::render($plans, rates()));
    assertContains('data-hash="'.$plans[0]['hash'].'"', Renderer::render($plans, rates()));

    $plans[0]['resources']['instance']['size'] = 'no-such-size';
    $html = Renderer::render($plans, rates());

    assertSame(2, substr_count($html, 'data-incomplete="1"'));
});

test('render: each environment is drawn as dashboard cards in a Network and a region group, marked from its steps', function () {
    $plans = threePlans();
    $html = Renderer::render($plans, rates());

    assertSame(3, substr_count($html, 'data-group="network"'));
    assertSame(6, substr_count($html, '<h4>EU West (London)</h4>'), 'a compute and a database column per environment');

    foreach (['Edge network', 'Domains', 'App cluster', 'Managed queue', 'Database'] as $card) {
        assertSame(3, substr_count($html, 'data-card="'.$card.'"'), $card);
    }

    assertContains('data-card="App cluster" data-action="create"', $html, 'a new environment is a create');
    assertContains('<span class="label">Compute</span><span class="v"><strong>Flex</strong> <span class="muted">1 GiB · 1 vCPU</span></span>', $html);
    assertContains('<span class="label">Scheduler</span><span class="v"><span class="dot on" aria-hidden="true"></span><span class="on-text">Enabled</span></span>', $html);
    assertContains('<span class="on-text">After 5 minutes</span>', $html);
    assertContains('<span class="label">Autoscaling</span><span class="v">up to 3 workers</span>', $html, 'no minimum declared');
    assertContains('<span class="label">Cluster</span><span class="v">example-nonprod <span class="muted">(new)</span></span>', $html);
    assertContains('<span class="label">Custom domains</span><span class="v"><span class="muted">none</span></span>', $html);

    $kept = planPreview(completePreviewState());
    $keptHtml = Renderer::render([$kept], rates());
    assertContains('data-card="App cluster" data-action="keep"', $keptHtml);
    assertContains('data-card="Database" data-action="keep"', $keptHtml);

    $changed = $kept;
    $changed['steps'][array_search('queue', array_column($changed['steps'], 'key'), true)]['action'] = 'change';
    assertContains('data-card="Managed queue" data-action="change"', Renderer::render([$changed], rates()));
});

test('render: a resource the plan does not declare has no card, and another region is named by its code', function () {
    $plan = threePlans()[0];
    unset($plan['resources']['queue'], $plan['resources']['database']);
    $plan['region'] = 'ap-south-9';
    $html = Renderer::render([$plan], rates());

    assertNotContains('data-card="Managed queue"', $html);
    assertNotContains('data-card="Database"', $html);
    assertContains('<h4>ap-south-9</h4>', $html);
    assertContains('data-card="App cluster"', $html);
});

test('render: each environment shows its plan code, and SKILL.md asks for approval by that code', function () {
    $plan = threePlans()[0];
    $plan['hash'] = PlanFile::hash($plan);
    $html = Renderer::render([$plan], rates());

    assertContains('data-plan-code="'.substr($plan['hash'], 0, 8).'"', $html);
    assertContains('>'.substr($plan['hash'], 0, 8).'</code>', $html);
    assertContains('Create {environment} as shown in the diagram at {link} (plan {code})?', (string) file_get_contents(skillDir().'/SKILL.md'));
});

test('render: the cards come first, then the secrets band, variables, steps and cost', function () {
    $html = Renderer::render([threePlans()[0]], rates());
    $positions = array_map(fn ($needle) => strpos($html, $needle), ['data-group="network"', '<h3>Steps</h3>', '<h3>Variables', 'Organisation-level secrets', '<h3>Estimated monthly cost</h3>']);

    assertTrue(! in_array(false, $positions, true), 'every part is on the page');
    assertSame(min($positions), $positions[0], 'the cards come before the rest');
});

test('render: the Domains card lists an existing domain of a kind that declares none, and the canvas fits a 320px screen', function () {
    $plan = threePlans()[1];
    $plan['state']['domains'] = [['id' => 'dom-1', 'name' => 'staging.example.test']];
    $html = Renderer::render([$plan], rates());

    assertContains('<span class="label">Custom domain</span><span class="v">staging.example.test</span>', $html);
    assertContains('.canvas { display: grid; grid-template-columns: minmax(0, 1fr);', $html, 'one column until the screen is wide');
    assertContains('After 5 minutes', $html);
});

test('render: the canvas is dotted, its columns are joined by lines on a wide screen and stack without them on a narrow one', function () {
    $html = Renderer::render([threePlans()[0]], rates());

    assertContains('background-image: radial-gradient(var(--dots) 1px, transparent 1px)', $html);
    assertSame(3, substr_count($html, '<div class="col" data-group='), 'network, compute and database columns');
    assertContains('.col + .col::before { content: ""; position: absolute;', $html);
    assertContains('@media (min-width: 1000px) { .canvas { grid-template-columns: repeat(auto-fit, minmax(0, 1fr));', $html);
    assertContains('@media (max-width: 999px) { .col + .col::before { display: none; } }', $html);
});

test('render: every card row has an icon, and on/off settings show a status dot with their word', function () {
    $html = Renderer::render([threePlans()[0]], rates());

    assertSame(substr_count($html, '<div class="row" '), preg_match_all('/<div class="row" data-row="[^"]+"><svg class="icon"/', $html));
    assertTrue(substr_count($html, '<div class="row" ') >= 15, 'the rows are counted');
    $off = threePlans()[0];
    $off['resources']['instance']['scale_to_zero'] = false;
    assertContains('<span class="label">Scale to zero</span><span class="v"><span class="dot off" aria-hidden="true"></span><span class="muted">Disabled</span>', Renderer::render([$off], rates()));
    assertContains('<span class="dot on" aria-hidden="true"></span><span class="on-text">Enabled</span>', $html);
});

test('render: sizes read as Cloud writes them, and an unknown size is shown as given', function () {
    $plan = threePlans()[0];
    $cases = ['flex-1gb' => '<strong>Flex</strong> <span class="muted">1 GiB · 1 vCPU</span>', 'flex-512mb' => '<strong>Flex</strong> <span class="muted">512 MiB · 1 vCPU</span>', 'pro.m-2vcpu-8gb' => '<strong>Pro</strong> <span class="muted">8 GiB · 2 vCPU</span>', 'mystery-size' => 'mystery-size'];

    foreach ($cases as $size => $expected) {
        $plan['resources']['instance']['size'] = $size;
        assertContains('<span class="label">Compute</span><span class="v">'.$expected.'</span>', Renderer::render([$plan], rates()), $size);
    }

    $plan['resources']['queue']['size'] = 'mq.flex.256mb';
    $html = Renderer::render([$plan], rates());
    assertContains('<span class="label">Memory</span><span class="v"><strong>Flex</strong> <span class="muted">256 MiB</span></span>', $html);
    assertContains('<span class="label">Type</span><span class="v">MySQL</span>', $html);
});

test('render: the steps are folded under the canvas, while secrets and cost stay open', function () {
    $html = Renderer::render([threePlans()[0]], rates());

    assertContains('<details class="steps"><summary><h3>Steps</h3>', $html);
    assertContains('<span class="show">Show</span></summary>', $html, 'the fold says it opens');
    assertContains('details.steps summary { cursor: pointer; }', $html, 'the summary keeps its disclosure marker');
    assertNotContains('<details class="steps" open', $html);
    assertTrue(strpos($html, 'data-step="1"') > strpos($html, '<details class="steps">'), 'the step rows are inside the fold');
    assertTrue(strpos($html, 'Organisation-level secrets') > strpos($html, '</details>'), 'the secrets band is outside it');
    assertTrue(strpos($html, '<h3>Estimated monthly cost</h3>') > strpos($html, '</details>'), 'the cost is outside it');
});

test('render: the Database card shows declared size, storage and scale to zero, and "set in the dashboard" only for one left out', function () {
    $plan = threePlans()[0];
    $plan['resources']['database'] += ['size' => 'mysql-flex-512mb', 'storage' => 5, 'scale_to_zero_after' => 300];
    $html = Renderer::render([$plan], rates());

    assertContains('<span class="label">Compute</span><span class="v"><strong>Flex</strong> <span class="muted">512 MiB · 1 vCPU</span></span></div><div class="row" data-row="Storage">', $html);
    assertContains('<span class="label">Storage</span><span class="v">5 GiB</span>', $html);
    assertContains('<span class="on-text">After 5 minutes</span></span></div><div class="row" data-row="Cluster">', $html);

    unset($plan['resources']['database']['storage']);
    assertContains('<span class="label">Storage</span><span class="v"><span class="muted">set in the dashboard</span></span>', Renderer::render([$plan], rates()));
    assertContains('<span class="label">Autoscaling</span><span class="v"><span class="muted">set in the dashboard</span></span>', $html, 'the app cluster autoscaling is not claimed');
});

test('cli: render writes one plan\'s diagram beside it as {environment}.html, titled by the environment', function () {
    $root = tempDir();
    runProcess(['git', 'init', '-q'], [], $root);
    file_put_contents("{$root}/.gitignore", ".cloud/plans/\n");
    file_put_contents("{$root}/cloud-environments.json", json_encode(validManifest()));
    $env = organisationOnly()->processEnv();
    runProcess(['php', skillDir().'/scripts/cloud-env.php', 'plan', 'preview', '--slug=x'], $env, $root);
    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'render', '.cloud/plans/preview-x.json'], $env, $root);

    assertSame(0, $run['exit'], $run['err']);
    $html = (string) @file_get_contents("{$root}/.cloud/plans/preview-x.html");
    assertContains('<title>preview-x plan</title>', $html);
    assertContains('<title>Environment plan diagram</title>', Renderer::render(threePlans(), rates()), 'several plans keep the general title');
});

test('SKILL.md requires publishing the diagram and naming its link in the approval', function () {
    $skill = (string) file_get_contents(skillDir().'/SKILL.md');

    assertContains('Publish `.cloud/plans/{environment}.html` as a private claude.ai artifact', $skill);
    assertContains('one link per environment', $skill);
    assertContains('republish to the same link whenever the plan changes', $skill);
    assertContains('Create {environment} as shown in the diagram at {link} (plan {code})?', $skill);
    assertContains('Without a published diagram there is no approval', $skill);
});

test('render: Cloud\'s database size names are written as the dashboard writes them', function () {
    $plan = threePlans()[0];

    foreach (['mysql-flex-512mb' => '<strong>Flex</strong> <span class="muted">512 MiB · 1 vCPU</span>', 'mysql-pro-16gb' => '<strong>Pro</strong> <span class="muted">16 GiB</span>'] as $size => $expected) {
        $plan['resources']['database']['size'] = $size;
        assertContains('data-row="Compute"><svg class="icon"', Renderer::render([$plan], rates()));
        assertContains('<span class="label">Compute</span><span class="v">'.$expected.'</span></div><div class="row" data-row="Storage">', Renderer::render([$plan], rates()), $size);
    }
});

test('SKILL.md names the minimum CLI version, the first-plan checks and where size names come from', function () {
    $skill = (string) file_get_contents(skillDir().'/SKILL.md');

    assertContains('Requires Cloud CLI 0.7.2 or later', $skill);
    assertContains('GitHub Apps → Laravel Cloud → Configure → Repository access', $skill);
    assertContains('The branch the environment tracks is pushed', $skill);
    assertContains('`cloud database:types`', $skill);
});

test('SKILL.md says PHP extensions need no dashboard step and are confirmed by verify', function () {
    $skill = (string) file_get_contents(skillDir().'/SKILL.md');

    assertContains('PHP extensions need no step', $skill);
    assertContains('`verify` confirms the manifest\'s `php.extensions` with `php -m`', $skill);
});

test('render: the App cluster card shows push to deploy when the kind declares it', function () {
    $manifest = validManifest();
    $manifest['kinds']['preview']['push_to_deploy'] = false;
    $plan = (new Planner(organisationOnly()->cloud(), $manifest))->plan('preview', 'x');

    assertContains('<span class="label">Push to deploy</span><span class="v"><span class="dot off" aria-hidden="true"></span><span class="muted">Disabled</span></span>', Renderer::render([$plan], rates()));
});

test('render: the Managed queue card shows the declared minimum', function () {
    $manifest = validManifest();
    $manifest['queue']['min_workers'] = 0;
    $plan = (new Planner(organisationOnly()->cloud(), $manifest))->plan('preview', 'x');

    assertContains('<span class="label">Autoscaling</span><span class="v">0–3 workers</span>', Renderer::render([$plan], rates()));
});

test('render: a card is marked change when only its settings step changes', function () {
    $plan = planPreview(completePreviewState());
    $plan['steps'][] = ['n' => 99, 'key' => 'queue-scaling', 'resource' => 'managed-queue', 'action' => 'change', 'command' => 'instance:update', 'args' => ['queue-1', '--min-replicas=0', '--force'], 'note' => 'minimum workers'];
    $plan['steps'][] = ['n' => 100, 'key' => 'cluster-settings', 'resource' => 'database-cluster', 'action' => 'change', 'command' => 'database-cluster:update', 'args' => ['cluster-1', '--storage=10', '--force'], 'note' => 'size, storage, scale to zero'];
    $html = Renderer::render([$plan], rates());

    assertContains('data-card="Managed queue" data-action="change"', $html);
    assertContains('data-card="Database" data-action="change"', $html);
    assertContains('data-card="App cluster" data-action="keep"', $html, 'control: an unchanged card stays keep');
});
