#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/lib/autoload.php';

use CloudEnvironments\Applier;
use CloudEnvironments\Cloud;
use CloudEnvironments\Manifest;
use CloudEnvironments\ManifestValidator;
use CloudEnvironments\PlanFile;
use CloudEnvironments\Planner;
use CloudEnvironments\Remover;
use CloudEnvironments\Renderer;
use CloudEnvironments\Secrets;
use CloudEnvironments\Terminal;
use CloudEnvironments\Verifier;

$command = $argv[1] ?? '';

if ($command === 'validate') {
    $path = $argv[2] ?? 'cloud-environments.json';
    $path = str_starts_with($path, '--manifest=') ? substr($path, strlen('--manifest=')) : $path;

    try {
        $errors = ManifestValidator::validate(Manifest::load($path));
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }

    if ($errors !== []) {
        fwrite(STDERR, implode("\n", $errors)."\n");
        exit(1);
    }

    echo "manifest valid: {$path}\n";
    exit(0);
}

if ($command === 'plan') {
    $options = [];
    $positional = [];

    foreach (array_slice($argv, 2) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $match) === 1) {
            $options[$match[1]] = $match[2];
        } else {
            $positional[] = $arg;
        }
    }

    $manifestPath = $options['manifest'] ?? 'cloud-environments.json';

    try {
        $manifest = Manifest::load($manifestPath);
        $errors = ManifestValidator::validate($manifest);

        if ($errors !== []) {
            fwrite(STDERR, implode("\n", $errors)."\n");
            exit(1);
        }

        $plan = (new Planner(Cloud::fromEnvironment(), $manifest))->plan($positional[0] ?? '', $options['slug'] ?? null, $options['branch'] ?? null);
        $path = PlanFile::write(dirname(realpath($manifestPath)), $plan);
    } catch (InvalidArgumentException | RuntimeException $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }

    foreach ($plan['steps'] as $step) {
        echo sprintf("%2d  %-6s  %-20s  %s\n", $step['n'], $step['action'], $step['resource'], $step['note']);
    }

    echo "plan: {$path}\nhash: ".PlanFile::hash($plan)."\n";
    exit(0);
}

if ($command === 'render') {
    $options = [];
    $files = [];

    foreach (array_slice($argv, 2) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $match) === 1) {
            $options[$match[1]] = $match[2];
        } else {
            $files[] = $arg;
        }
    }

    if ($files === []) {
        fwrite(STDERR, "usage: cloud-env.php render {plan-file}... [--out=file.html]\n");
        exit(2);
    }

    try {
        $plans = array_map(PlanFile::read(...), $files);
        $rates = json_decode((string) file_get_contents(__DIR__.'/../references/rates.json'), true, 512, JSON_THROW_ON_ERROR);
        $html = Renderer::render($plans, $rates);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }

    // One plan's diagram sits beside it as {environment}.html, where `apply` looks for it.
    $out = $options['out'] ?? dirname(realpath($files[0])).'/'.(count($plans) === 1 ? $plans[0]['environment'] : 'diagram').'.html';
    if (@file_put_contents($out, $html) === false) {
        fwrite(STDERR, "could not write {$out}\n");
        exit(1);
    }

    echo "diagram: {$out}\n";
    exit(0);
}

if ($command === 'secrets') {
    $manifestPath = 'cloud-environments.json';
    $fromStdin = false;
    $status = false;

    foreach (array_slice($argv, 2) as $arg) {
        if (str_starts_with($arg, '--manifest=')) {
            $manifestPath = substr($arg, strlen('--manifest='));
        } elseif ($arg === '--from-stdin') {
            $fromStdin = true;
        } elseif ($arg === 'status') {
            $status = true;
        } else {
            fwrite(STDERR, "usage: cloud-env.php secrets [status] [--from-stdin] [--manifest={path}]\n");
            exit(2);
        }
    }

    try {
        $manifest = Manifest::load($manifestPath);
        $errors = ManifestValidator::validate($manifest);

        if ($errors !== []) {
            fwrite(STDERR, implode("\n", $errors)."\n");
            exit(1);
        }

        $secrets = new Secrets(Cloud::fromEnvironment(), Cloud::fromEnvironment(true), $manifest, $manifestPath);
        $lines = $status ? $secrets->status() : $secrets->setup(new Terminal(STDIN, STDOUT), $fromStdin);
    } catch (InvalidArgumentException | RuntimeException $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }

    echo implode("\n", $lines)."\n";
    $problems = array_filter($lines, fn ($line) => ! str_ends_with($line, ': present') && ! str_contains($line, 'written to the manifest'));
    exit($problems === [] ? 0 : 1);
}

if (in_array($command, ['apply', 'verify', 'remove', 'preview'], true)) {
    $options = [];
    $positional = [];

    foreach (array_slice($argv, 2) as $arg) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $match) === 1) {
            $options[$match[1]] = $match[2];
        } else {
            $positional[] = $arg;
        }
    }

    try {
        $manifest = Manifest::load($options['manifest'] ?? 'cloud-environments.json');
        $errors = ManifestValidator::validate($manifest);

        if ($errors !== []) {
            fwrite(STDERR, implode("\n", $errors)."\n");
            exit(1);
        }

        $reader = Cloud::fromEnvironment();

        if ($command === 'apply') {
            if (! isset($positional[0], $options['hash'])) {
                fwrite(STDERR, "usage: cloud-env.php apply {plan-file} --hash={approved hash}\n");
                exit(2);
            }

            // The diagram is matched against the plan's own hash, so a wrong --hash is reported
            // by the hash check below rather than as a missing diagram.
            $plan = PlanFile::read($positional[0]);
            $diagram = dirname((string) realpath($positional[0])).'/'.$plan['environment'].'.html';

            if (! is_file($diagram) || ! str_contains((string) file_get_contents($diagram), 'data-hash="'.($plan['hash'] ?? '').'"')) {
                fwrite(STDERR, "no diagram of this plan at {$diagram}: run `render` on the plan, publish the page as a private artifact, and get the plan code approved\n");
                exit(1);
            }

            if (isset($options['code']) && ! str_starts_with($options['hash'], $options['code'])) {
                fwrite(STDERR, "the hash does not belong to the approved plan code {$options['code']}; make and approve a new plan\n");
                exit(1);
            }

            $lines = (new Applier(Cloud::fromEnvironment(true), $reader, $manifest))->apply($plan, $options['hash']);
        } elseif ($command === 'verify') {
            if (! isset($positional[0])) {
                fwrite(STDERR, "usage: cloud-env.php verify {environment} [--branch=] [--extensions=no]\n");
                exit(2);
            }

            $failures = (new Verifier($reader, $manifest, Cloud::fromEnvironment(true)))->verify($positional[0] ?? '', $options['branch'] ?? null, ($options['extensions'] ?? 'yes') !== 'no');

            if ($failures !== []) {
                fwrite(STDERR, implode("\n", $failures)."\n");
                exit(1);
            }

            $lines = ['verified: '.($positional[0] ?? '').' matches the manifest'];
        } elseif ($command === 'remove') {
            $remover = new Remover(Cloud::fromEnvironment(true), $reader, $manifest);

            if (($positional[0] ?? '') === 'environment' && isset($positional[1])) {
                $lines = $remover->removeEnvironment($positional[1], $options['confirm'] ?? null);
            } elseif (($positional[0] ?? '') === 'domain' && isset($positional[1], $positional[2])) {
                $lines = $remover->removeDomain($positional[1], $positional[2], $options['confirm'] ?? null);
            } else {
                fwrite(STDERR, "usage: cloud-env.php remove environment {name} --confirm={name} | remove domain {environment} {domain} --confirm={domain}\n");
                exit(2);
            }
        } else {
            if (($positional[0] ?? '') !== 'down' || ! isset($positional[1])) {
                fwrite(STDERR, "usage: cloud-env.php preview down {slug} --confirm=preview-{slug}\n");
                exit(2);
            }

            $name = str_starts_with($positional[1], 'preview-') ? $positional[1] : 'preview-'.$positional[1];
            $lines = (new Remover(Cloud::fromEnvironment(true), $reader, $manifest))->removeEnvironment($name, $options['confirm'] ?? null, true);
        }
    } catch (InvalidArgumentException | RuntimeException $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }

    echo implode("\n", $lines)."\n";
    exit(0);
}

fwrite(STDERR, "usage: cloud-env.php validate [manifest] | render {plan}... | plan | apply | verify | remove | preview down | secrets [status] [--from-stdin] {kind} [--slug=] [--branch=] [--manifest=]\n");
exit(2);
