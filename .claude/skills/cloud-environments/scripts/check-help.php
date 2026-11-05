#!/usr/bin/env php
<?php

declare(strict_types=1);

// Compares the command catalog with `{CLOUD_BIN} {command} -h`: every command must exist and
// show every option the skill passes it. Reads help text only; makes no change in Cloud.

require __DIR__.'/lib/autoload.php';

use CloudEnvironments\Catalog;

$bin = getenv('CLOUD_BIN') ?: 'cloud';
$problems = [];
$checked = 0;

foreach (Catalog::commands() as $command => $entry) {
    $process = proc_open([$bin, $command, '-h', '--no-ansi'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $help = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);
    $exit = proc_close($process);

    if ($exit !== 0 || ! str_contains($help, 'Usage:')) {
        $problems[] = "{$command}: no help text (exit {$exit})";

        continue;
    }

    foreach (array_merge($entry['options'], Catalog::COMMON) as $option) {
        $checked++;

        if (preg_match('/'.preg_quote($option, '/').'(?![a-z-])/', $help) !== 1) {
            $problems[] = "{$command}: option {$option} is not in its help";
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, implode("\n", $problems)."\n");
    exit(1);
}

echo 'checked '.count(Catalog::commands())." commands and {$checked} options against {$bin}\n";
