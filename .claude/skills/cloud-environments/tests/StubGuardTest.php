<?php

declare(strict_types=1);

use CloudEnvironments\Manifest;

test('guard: the suite refuses to run when CLOUD_BIN is not the stub, and names the binary', function () {
    $run = runProcess(['php', skillDir().'/tests/run.php', 'no-such-test'], ['CLOUD_BIN' => '/bin/echo']);

    assertSame(2, $run['exit']);
    assertContains('refusing to run', $run['err']);
    assertContains('/bin/echo', $run['err']);
    assertNotContains('ok ', $run['out']);
});

test('guard control: with the stub as CLOUD_BIN the suite starts', function () {
    $run = runProcess(['php', skillDir().'/tests/run.php', 'no-such-test'], ['CLOUD_BIN' => skillDir().'/tests/stub/cloud']);

    assertNotContains('refusing to run', $run['err']);
    assertSame(3, $run['exit']);
    assertContains('no test matched', $run['err']);
});

test('stub records each call it receives', function () {
    $log = tempDir().'/calls.log';
    runProcess([skillDir().'/tests/stub/cloud', 'application:list', '--json'], ['CLOUD_STUB_LOG' => $log]);

    assertSame(['argv' => ['application:list', '--json'], 'write' => false], json_decode(trim((string) file_get_contents($log)), true));
});
