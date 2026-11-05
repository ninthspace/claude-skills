<?php

declare(strict_types=1);

function plantedTerm(): string
{
    return 'zebra'.'corp';
}

function scan(string $termsFile, ?string $dir = null): array
{
    $args = [skillDir().'/scripts/agnostic-scan.sh', $termsFile];

    if ($dir !== null) {
        $args[] = $dir;
    }

    return runProcess($args);
}

test('scan: a planted file holding a term fails, matched case-insensitively', function () {
    $dir = tempDir();
    $terms = "{$dir}/terms.txt";
    file_put_contents($terms, "# comment\n\n".plantedTerm()."\n");
    mkdir("{$dir}/scan");
    file_put_contents("{$dir}/scan/planted.txt", "deploys for ".strtoupper(plantedTerm())."\n");

    $run = scan($terms, "{$dir}/scan");

    assertSame(1, $run['exit']);
    assertContains('FLAG term planted.txt', $run['out']);
});

test('scan: a planted file holding a Cloud ID fails', function () {
    $dir = tempDir();
    $terms = "{$dir}/terms.txt";
    file_put_contents($terms, plantedTerm()."\n");
    mkdir("{$dir}/scan");
    file_put_contents("{$dir}/scan/planted.json", '{"id":"'.'app-'.str_repeat('a', 8).'-1234"}');

    $run = scan($terms, "{$dir}/scan");

    assertSame(1, $run['exit']);
    assertContains('FLAG cloud-id planted.json', $run['out']);
});

test('scan control: a directory with neither passes', function () {
    $dir = tempDir();
    file_put_contents("{$dir}/terms.txt", plantedTerm()."\n");
    mkdir("{$dir}/scan");
    file_put_contents("{$dir}/scan/clean.txt", "nothing here\n");

    assertSame(0, scan("{$dir}/terms.txt", "{$dir}/scan")['exit']);
});

test('scan floor: the scanned set holds SKILL.md, every script and every fixture', function () {
    $dir = tempDir();
    file_put_contents("{$dir}/terms.txt", plantedTerm()."\n");
    $run = scan("{$dir}/terms.txt");

    assertSame(0, $run['exit'], $run['out']);

    $expected = ['SKILL.md'];

    foreach (glob(skillDir().'/scripts/*') as $path) {
        if (is_file($path)) {
            $expected[] = 'scripts/'.basename($path);
        }
    }

    foreach (glob(skillDir().'/scripts/lib/*') as $path) {
        $expected[] = 'scripts/lib/'.basename($path);
    }

    foreach (glob(skillDir().'/tests/fixtures/*') as $path) {
        $expected[] = 'tests/fixtures/'.basename($path);
    }

    assertTrue(count($expected) >= 8, 'the floor has members');

    foreach ($expected as $file) {
        assertContains("scanned {$file}\n", $run['out']);
    }
});

test('scan: the project terms file finds nothing in the skill', function () {
    $terms = getenv('AGNOSTIC_TERMS');

    if ($terms === false || $terms === '') {
        throw new SkipTest('set AGNOSTIC_TERMS to a terms file');
    }

    $run = scan($terms);

    assertSame(0, $run['exit'], $run['out']);
});
