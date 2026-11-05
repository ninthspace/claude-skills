<?php

declare(strict_types=1);

use CloudEnvironments\Catalog;

function fakeCloud(array $missing): string
{
    $dir = tempDir();
    $template = <<<'PHP'
#!/usr/bin/env php
<?php
$missing = __MISSING__;
$all = __ALL__;
$c = $argv[1] ?? '';
if (! isset($all[$c])) {
    exit(1);
}
echo "Usage:\n  {$c} [options]\n";
foreach (array_merge($all[$c]['options'], ['--json', '--no-interaction', '--show-sensitive']) as $o) {
    if (! in_array("{$c} {$o}", $missing, true)) {
        echo "      {$o}[=X]  text\n";
    }
}
PHP;
    file_put_contents("{$dir}/cloud", str_replace(['__MISSING__', '__ALL__'], [var_export($missing, true), var_export(Catalog::commands(), true)], $template));
    chmod("{$dir}/cloud", 0755);

    return "{$dir}/cloud";
}

test('help check: every catalog command and option present in the help passes', function () {
    $run = runProcess(['php', skillDir().'/scripts/check-help.php'], ['CLOUD_BIN' => fakeCloud([])]);

    assertSame(0, $run['exit'], $run['err']);
    assertContains('checked '.count(Catalog::commands()).' commands', $run['out']);
});

test('help check control: an option the help lacks is named and fails', function () {
    $run = runProcess(['php', skillDir().'/scripts/check-help.php'], ['CLOUD_BIN' => fakeCloud(['environment:update --database-id'])]);

    assertSame(1, $run['exit']);
    assertContains('environment:update: option --database-id is not in its help', $run['err']);
});
