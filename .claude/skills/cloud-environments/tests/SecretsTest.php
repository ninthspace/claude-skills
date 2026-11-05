<?php

declare(strict_types=1);

use CloudEnvironments\Planner;
use CloudEnvironments\PlanRefused;
use CloudEnvironments\Secrets;
use CloudEnvironments\Terminal;

/**
 * A terminal fed from a string, reporting itself as interactive or not, that records each
 * prompt and whether the value was read hidden.
 */
final class FakeTerminal extends Terminal
{
    /** @var list<string> */
    public array $prompts = [];

    /** @var list<string> */
    private array $lines;

    public function __construct(string $input, private readonly bool $interactive = true)
    {
        $this->lines = explode("\n", $input);
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    public function say(string $line): void {}

    public function readValue(string $prompt): ?string
    {
        $this->prompts[] = ($this->interactive ? '[hidden] ' : '').$prompt;

        return array_shift($this->lines);
    }
}

/**
 * The example manifest without secret IDs, written where `secrets` can update it.
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function manifestWithoutIds(): array
{
    $manifest = validManifest();

    foreach ($manifest['secrets'] as &$secret) {
        unset($secret['id']);
    }

    $path = tempDir().'/cloud-environments.json';
    file_put_contents($path, json_encode($manifest));

    return [$path, $manifest];
}

function secretsFor(World $world, string $path): Secrets
{
    return new Secrets($world->cloud(), $world->cloud(true), json_decode((string) file_get_contents($path), true), $path);
}

function emptySecretsWorld(): World
{
    $world = new World();
    $world->change(function (array &$w) { $w['secrets'] = []; });

    return $world;
}

test('secrets: it creates each missing secret with its key and tag, from stdin, and writes the ID into the manifest', function () {
    $world = emptySecretsWorld();
    [$path] = manifestWithoutIds();
    $log = secretsFor($world, $path)->setup(new FakeTerminal("test-value-1131\nlive-value-2242"));

    $created = $world->read()['secrets'];
    assertSame(2, count($created));
    assertSame(['SERVICE_PASSWORD', 'SERVICE_PASSWORD'], array_column($created, 'key'));
    assertSame(['cloud-environments:example-app:SERVICE_PASSWORD:test', 'cloud-environments:example-app:SERVICE_PASSWORD:live'], array_column($created, 'notes'));
    assertSame(hash('sha256', 'test-value-1131'), $created[0]['value_sha'], 'the value arrived on stdin');

    $written = json_decode((string) file_get_contents($path), true);
    assertSame(array_column($created, 'id'), array_column($written['secrets'], 'id'));
    assertContains('created', implode("\n", $log));
});

test('secrets control: an entry with an ID, or whose tagged secret exists, is not created again, and the tagged one is adopted', function () {
    $world = emptySecretsWorld();
    $world->change(function (array &$w) {
        $w['secrets'][] = ['id' => 'scrt-test', 'key' => 'SERVICE_PASSWORD', 'notes' => 'cloud-environments:example-app:SERVICE_PASSWORD:test'];
        $w['secrets'][] = ['id' => 'scrt-live', 'key' => 'SERVICE_PASSWORD', 'notes' => 'other'];
    });
    [$path, $manifest] = manifestWithoutIds();
    $manifest['secrets'][1]['id'] = 'scrt-live';
    file_put_contents($path, json_encode($manifest));
    $terminal = new FakeTerminal('');
    secretsFor($world, $path)->setup($terminal);

    assertSame([], $terminal->prompts, 'no value was asked for');
    assertSame([], $world->writes(), 'nothing was created');
    assertSame(['scrt-test', 'scrt-live'], array_column(json_decode((string) file_get_contents($path), true)['secrets'], 'id'));
});

test('secrets: a value is never in the arguments, the output, an error or the manifest', function () {
    $world = emptySecretsWorld();
    [$path] = manifestWithoutIds();
    $log = secretsFor($world, $path)->setup(new FakeTerminal("test-value-1131\nlive-value-2242"));
    $everything = json_encode($world->calls()).implode("\n", $log).file_get_contents($path);

    assertNotContains('test-value-1131', $everything);
    assertNotContains('live-value-2242', $everything);

    $failing = emptySecretsWorld();
    $failing->extra['CLOUD_STUB_FAIL'] = 'secret:create';
    [$path2] = manifestWithoutIds();

    try {
        secretsFor($failing, $path2)->setup(new FakeTerminal("test-value-1131\nx"));
        fail('expected an error');
    } catch (RuntimeException $e) {
        assertNotContains('test-value-1131', $e->getMessage());
    }
});

test('secrets control: a value passed as --value shows in the recorded arguments', function () {
    $world = emptySecretsWorld();
    try {
        $world->cloud(true)->json(['secret:create', '--name=SERVICE_PASSWORD', '--value=visible-3353']);
        fail('the catalog accepted --value');
    } catch (InvalidArgumentException $e) {
        assertContains('--value', $e->getMessage(), 'the catalog refuses --value');
    }

    $run = runProcess([skillDir().'/tests/stub/cloud', 'secret:create', '--name=SERVICE_PASSWORD', '--value=visible-3353'], $world->processEnv());
    assertSame(0, $run['exit']);
    assertContains('visible-3353', json_encode($world->calls()));
});

test('secrets: on a terminal each value is read hidden with a prompt naming key, class and kinds; without one it refuses unless --from-stdin', function () {
    $world = emptySecretsWorld();
    [$path] = manifestWithoutIds();
    $terminal = new FakeTerminal("a\nb");
    secretsFor($world, $path)->setup($terminal);

    assertSame(['[hidden] Value for SERVICE_PASSWORD (test, preview, staging): ', '[hidden] Value for SERVICE_PASSWORD (live, production): '], $terminal->prompts);

    [$path2] = manifestWithoutIds();

    try {
        secretsFor(emptySecretsWorld(), $path2)->setup(new FakeTerminal("a\nb", false));
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('--from-stdin', $e->getMessage());
    }

    $piped = emptySecretsWorld();

    try {
        secretsFor($piped, $path2)->setup(new FakeTerminal("a\nb", false), true);
    } catch (PlanRefused $e) {
        fail('--from-stdin was refused: '.$e->getMessage());
    }

    assertSame(2, count($piped->read()['secrets']), 'control: --from-stdin reads the piped values');
});

test('secrets: a duplicated tag is reported and nothing is created for it; an empty value skips the entry', function () {
    $world = emptySecretsWorld();
    $world->change(function (array &$w) {
        $w['secrets'][] = ['id' => 'scrt-a', 'key' => 'SERVICE_PASSWORD', 'notes' => 'cloud-environments:example-app:SERVICE_PASSWORD:test'];
        $w['secrets'][] = ['id' => 'scrt-b', 'key' => 'SERVICE_PASSWORD', 'notes' => 'cloud-environments:example-app:SERVICE_PASSWORD:test'];
    });
    [$path] = manifestWithoutIds();
    $log = implode("\n", secretsFor($world, $path)->setup(new FakeTerminal('')));

    assertContains('SERVICE_PASSWORD (test, preview, staging): DUPLICATE — 2 secrets carry its tag (scrt-a, scrt-b)', $log);
    assertContains('SERVICE_PASSWORD (live, production): skipped (no value)', $log);
    assertSame([], $world->writes());
});

test('secrets status: each secret is present, missing, duplicated or unknown, without values, and the CLI exits 1 unless all are present', function () {
    $world = emptySecretsWorld();
    $world->change(function (array &$w) {
        $w['secrets'][] = ['id' => 'scrt-live', 'key' => 'SERVICE_PASSWORD', 'notes' => 'cloud-environments:example-app:SERVICE_PASSWORD:live'];
    });
    [$path, $manifest] = manifestWithoutIds();
    $manifest['secrets'][1]['id'] = 'scrt-live';
    file_put_contents($path, json_encode($manifest));

    assertSame(['SERVICE_PASSWORD (test): missing', 'SERVICE_PASSWORD (live): present'], secretsFor($world, $path)->status());

    $manifest['secrets'][0]['id'] = 'scrt-gone';
    file_put_contents($path, json_encode($manifest));
    assertContains('unknown — scrt-gone', implode("\n", secretsFor($world, $path)->status()));

    $cli = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'secrets', 'status', "--manifest={$path}"], $world->processEnv());
    assertSame(1, $cli['exit']);

    $manifest['secrets'][0]['id'] = 'scrt-live';
    $world->change(function (array &$w) {
        $w['secrets'][] = ['id' => 'scrt-t', 'key' => 'SERVICE_PASSWORD', 'notes' => 'cloud-environments:example-app:SERVICE_PASSWORD:test'];
    });
    $manifest['secrets'][0]['id'] = 'scrt-t';
    file_put_contents($path, json_encode($manifest));
    $ok = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'secrets', 'status', "--manifest={$path}"], $world->processEnv());
    assertSame(0, $ok['exit'], $ok['out'].$ok['err']);
});

test('plan: a needed secret with no ID is refused, naming its key and `secrets`', function () {
    [$path, $manifest] = manifestWithoutIds();

    try {
        (new Planner(organisationOnly()->cloud(), $manifest))->plan('preview', 'x');
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('SERVICE_PASSWORD (test) has no secret ID yet; run `secrets`', $e->getMessage());
    }
});

test('cli: secrets refuses without a terminal and creates from --from-stdin', function () {
    $world = emptySecretsWorld();
    [$path] = manifestWithoutIds();
    $cli = ['php', skillDir().'/scripts/cloud-env.php', 'secrets', "--manifest={$path}"];

    $refused = runProcess($cli, $world->processEnv());
    assertSame(1, $refused['exit']);
    assertContains('--from-stdin', $refused['err']);

    $process = proc_open([...$cli, '--from-stdin'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), $world->processEnv()));
    fwrite($pipes[0], "piped-value-1\npiped-value-2\n");
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $exit = proc_close($process);

    assertSame(0, $exit, $out);
    assertSame(2, count($world->read()['secrets']));
    assertNotContains('piped-value', $out);
});

test('secrets: a manifest with some IDs missing still plans and verifies the kinds whose secrets are present', function () {
    $world = new World();
    $manifest = validManifest();
    unset($manifest['secrets'][1]['id']);

    try {
        $plan = worldPlan($world, $manifest);
    } catch (\Throwable $e) {
        fail('plan failed with a live secret ID missing: '.$e->getMessage());
    }

    applyOrFail(applier($world, $manifest), $plan);
    changePreview($world, function (array &$e) { $e['phpMajorVersion'] = '8.5'; });

    assertSame([], (new \CloudEnvironments\Verifier($world->cloud(), $manifest, $world->cloud(true)))->verify('preview-x'));

    $noPreviewId = validManifest();
    unset($noPreviewId['secrets'][0]['id']);
    assertContains('SERVICE_PASSWORD (test) has no ID', implode("\n", (new \CloudEnvironments\Verifier($world->cloud(), $noPreviewId, $world->cloud(true)))->verify('preview-x')));
});

test('validator: a key with two entries of the same class is refused, so each tag names one secret', function () {
    $m = validManifest();
    $m['secrets'][0]['kinds'] = ['preview'];
    $m['secrets'][] = ['key' => 'SERVICE_PASSWORD', 'class' => 'test', 'kinds' => ['staging']];

    assertErrorNames('secrets[2]', \CloudEnvironments\ManifestValidator::validate($m));
});

test('secrets: an ID the organisation does not hold is replaced, and the log names it', function () {
    $world = emptySecretsWorld();
    [$path, $manifest] = manifestWithoutIds();
    $manifest['secrets'][0]['id'] = 'scrt-gone';
    file_put_contents($path, json_encode($manifest));
    $log = implode("\n", secretsFor($world, $path)->setup(new FakeTerminal("a\nb")));

    assertContains('replacing scrt-gone', $log);
    assertSame(2, count($world->read()['secrets']));
});

test('secrets status: it checks the organisation first', function () {
    $world = emptySecretsWorld();
    $world->change(function (array &$w) { $w['organisation'] = 'someone-else'; });
    [$path] = manifestWithoutIds();

    try {
        secretsFor($world, $path)->status();
        fail('expected a refusal');
    } catch (PlanRefused $e) {
        assertContains('someone-else', $e->getMessage());
    }
});

test('secrets: the manifest is written with two-space indentation, and an unknown argument prints the usage', function () {
    $world = emptySecretsWorld();
    [$path] = manifestWithoutIds();
    secretsFor($world, $path)->setup(new FakeTerminal("a\nb"));

    assertContains("\n  \"organisation\"", (string) file_get_contents($path));
    assertNotContains("\n    \"organisation\"", (string) file_get_contents($path));

    $run = runProcess(['php', skillDir().'/scripts/cloud-env.php', 'secrets', 'stauts', "--manifest={$path}"], $world->processEnv());
    assertSame(2, $run['exit']);
    assertContains('usage: cloud-env.php secrets', $run['err']);
});
