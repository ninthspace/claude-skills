<?php

declare(strict_types=1);

namespace CloudEnvironments;

use InvalidArgumentException;

/**
 * Every Cloud CLI command the skill may emit, with the options it passes and whether it
 * writes. `scripts/check-help.php` compares this table with `cloud {command} -h`, so a
 * command or option the CLI does not have is found before a plan reaches Cloud.
 */
final class Catalog
{
    /** Options every command may take. */
    public const COMMON = ['--json', '--no-interaction', '--show-sensitive'];

    /**
     * @return array<string, array{write: bool, options: list<string>}>
     */
    public static function commands(): array
    {
        return [
            'application:list' => ['write' => false, 'options' => []],
            'environment:list' => ['write' => false, 'options' => []],
            'environment:get' => ['write' => false, 'options' => []],
            'instance:list' => ['write' => false, 'options' => []],
            'database-cluster:list' => ['write' => false, 'options' => []],
            'secret:list' => ['write' => false, 'options' => []],
            'environment-secret:list' => ['write' => false, 'options' => []],
            'domain:list' => ['write' => false, 'options' => []],
            'command:run' => ['write' => true, 'options' => ['--cmd']],
            'application:create' => ['write' => true, 'options' => ['--name', '--repository', '--region']],
            'environment:create' => ['write' => true, 'options' => ['--name', '--branch']],
            'environment:update' => ['write' => true, 'options' => ['--branch', '--build-command', '--deploy-command', '--database-id', '--uses-push-to-deploy', '--force']],
            'instance:update' => ['write' => true, 'options' => ['--size', '--uses-scheduler', '--scale-to-zero', '--scale-to-zero-timeout', '--min-replicas', '--max-replicas', '--force']],
            'database-cluster:create' => ['write' => true, 'options' => ['--name', '--type', '--region', '--size', '--storage', '--suspend-seconds', '--create-default-database']],
            'database-cluster:update' => ['write' => true, 'options' => ['--size', '--storage', '--suspend-seconds', '--force']],
            'database:create' => ['write' => true, 'options' => ['--name']],
            'managed-queue:create' => ['write' => true, 'options' => ['--name', '--size', '--max-workers']],
            'environment:variables' => ['write' => true, 'options' => ['--action', '--key', '--value', '--force']],
            'environment-secret:attach' => ['write' => true, 'options' => []],
            'domain:create' => ['write' => true, 'options' => ['--name']],
            'domain:delete' => ['write' => true, 'options' => ['--force']],
            'secret:create' => ['write' => true, 'options' => ['--name', '--notes']],
            'environment:delete' => ['write' => true, 'options' => ['--force']],
            'database:delete' => ['write' => true, 'options' => ['--force']],
        ];
    }

    public static function isWrite(string $command): bool
    {
        $entry = self::commands()[$command] ?? null;

        if ($entry === null) {
            throw new InvalidArgumentException("command {$command} is not in the catalog");
        }

        return $entry['write'];
    }

    /**
     * Refuses a command or option the catalog does not hold.
     *
     * @param  list<string>  $args  the arguments after the command
     */
    public static function assertEmittable(string $command, array $args): void
    {
        $entry = self::commands()[$command] ?? null;

        if ($entry === null) {
            throw new InvalidArgumentException("command {$command} is not in the catalog");
        }

        foreach ($args as $arg) {
            if (! str_starts_with($arg, '--')) {
                continue;
            }

            $option = explode('=', $arg, 2)[0];

            if (! in_array($option, array_merge($entry['options'], self::COMMON), true)) {
                throw new InvalidArgumentException("option {$option} is not in the catalog for {$command}");
            }
        }
    }
}
