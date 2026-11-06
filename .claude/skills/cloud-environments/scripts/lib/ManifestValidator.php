<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Checks a manifest and returns one message per problem, each beginning with the field it
 * concerns ("kinds.preview.branch: missing").
 */
final class ManifestValidator
{
    private const NAME = '/^[A-Z][A-Z0-9_]*$/';

    /**
     * @param  array<string, mixed>  $m
     * @return list<string>
     */
    public static function validate(array $m): array
    {
        $errors = [];

        foreach (['organisation', 'application', 'repository', 'region'] as $field) {
            if (! isset($m[$field]) || ! is_string($m[$field]) || trim($m[$field]) === '') {
                $errors[] = "{$field}: missing";
            }
        }

        self::variables($m['variables'] ?? [], 'variables', $errors);
        self::sections($m, $errors);
        self::kinds($m, $errors);
        $declared = is_array($m['variables'] ?? null) ? $m['variables'] : [];

        foreach (Manifest::KINDS as $kind) {
            $own = $m['kinds'][$kind]['variables'] ?? [];
            $declared += is_array($own) ? $own : [];
        }

        self::secrets($m['secrets'] ?? [], $errors, $declared);

        return $errors;
    }

    /**
     * The sections the planner reads, so a plan never carries an empty or mistyped argument.
     *
     * @param  array<string, mixed>  $m
     * @param  list<string>  $errors
     */
    private static function sections(array $m, array &$errors): void
    {
        foreach (['build_command', 'deploy_command'] as $field) {
            if (isset($m[$field]) && (! is_string($m[$field]) || trim($m[$field]) === '')) {
                $errors[] = "{$field}: must be a non-empty string";
            }
        }

        if (isset($m['php'])) {
            $php = $m['php'];

            if (! is_array($php) || ! is_string($php['version'] ?? null) || preg_match('/^\d+\.\d+$/', $php['version']) !== 1) {
                $errors[] = 'php.version: must be a major.minor string such as "8.5"';
            }

            $extensions = is_array($php) ? ($php['extensions'] ?? []) : [];

            if (! is_array($extensions) || ! array_is_list($extensions) || array_filter($extensions, fn ($e) => ! is_string($e) || trim($e) === '') !== []) {
                $errors[] = 'php.extensions: must be a list of extension names';
            }
        }

        if (isset($m['instance'])) {
            $i = $m['instance'];
            $ok = is_array($i);
            self::typed($ok ? $i : [], 'size', 'string', 'instance', $errors, true);
            self::typed($ok ? $i : [], 'scheduler', 'boolean', 'instance', $errors);
            self::typed($ok ? $i : [], 'scale_to_zero', 'boolean', 'instance', $errors);
            self::typed($ok ? $i : [], 'sleep_timeout', 'integer', 'instance', $errors, $ok && ($i['scale_to_zero'] ?? false) === true);

            if ($ok && is_int($i['sleep_timeout'] ?? null) && $i['sleep_timeout'] < 1) {
                $errors[] = 'instance.sleep_timeout: must be at least 1 minute';
            }

            if (! $ok) {
                $errors[] = 'instance: must be an object';
            }
        }

        if (isset($m['queue'])) {
            $q = $m['queue'];
            $ok = is_array($q);
            self::typed($ok ? $q : [], 'size', 'string', 'queue', $errors, true);
            self::typed($ok ? $q : [], 'max_workers', 'integer', 'queue', $errors, true);
            self::typed($ok ? $q : [], 'name', 'string', 'queue', $errors);
            self::typed($ok ? $q : [], 'min_workers', 'integer', 'queue', $errors);

            if ($ok && is_int($q['min_workers'] ?? null) && ($q['min_workers'] < 0 || (is_int($q['max_workers'] ?? null) && $q['min_workers'] > $q['max_workers']))) {
                $errors[] = 'queue.min_workers: must be 0 up to max_workers';
            }

            if (! $ok) {
                $errors[] = 'queue: must be an object';
            }
        }

        $databases = $m['databases'] ?? [];

        if (! is_array($databases)) {
            $errors[] = 'databases: must be an object';

            return;
        }

        foreach ($databases as $key => $database) {
            $database = is_array($database) ? $database : [];
            self::typed($database, 'name', 'string', "databases.{$key}", $errors, true);
            self::typed($database, 'type', 'string', "databases.{$key}", $errors, true);
            self::typed($database, 'size', 'string', "databases.{$key}", $errors);
            self::typed($database, 'storage', 'integer', "databases.{$key}", $errors);
            self::typed($database, 'scale_to_zero_after', 'integer', "databases.{$key}", $errors);

            if (is_int($database['storage'] ?? null) && $database['storage'] < 5) {
                $errors[] = "databases.{$key}.storage: Cloud's smallest storage is 5 GB";
            }

            if (is_int($database['scale_to_zero_after'] ?? null) && ($database['scale_to_zero_after'] < 0 || $database['scale_to_zero_after'] > 3600)) {
                $errors[] = "databases.{$key}.scale_to_zero_after: must be 0 (never) to 3600 seconds";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  list<string>  $errors
     */
    private static function typed(array $section, string $field, string $type, string $at, array &$errors, bool $required = false): void
    {
        if (! array_key_exists($field, $section)) {
            if ($required) {
                $errors[] = "{$at}.{$field}: missing";
            }

            return;
        }

        if (gettype($section[$field]) !== $type || $section[$field] === '') {
            $errors[] = "{$at}.{$field}: must be a non-empty {$type}";
        }
    }

    /**
     * @param  array<string, mixed>  $m
     * @param  list<string>  $errors
     */
    private static function kinds(array $m, array &$errors): void
    {
        $kinds = $m['kinds'] ?? null;

        if (! is_array($kinds) || $kinds === []) {
            $errors[] = 'kinds: missing';

            return;
        }

        foreach ($kinds as $kind => $config) {
            $at = "kinds.{$kind}";

            if (! in_array($kind, Manifest::KINDS, true)) {
                $errors[] = "{$at}: unknown environment kind (allowed: ".implode(', ', Manifest::KINDS).')';

                continue;
            }

            self::branch($config['branch'] ?? null, "{$at}.branch", $errors);

            if ($kind !== 'preview' && (($config['branch']['rule'] ?? null) === 'slug')) {
                $errors[] = "{$at}.branch.rule: {$kind} needs a fixed branch, since it has no slug";
            }

            foreach (['build_command', 'deploy_command'] as $field) {
                if (is_array($config) && array_key_exists($field, $config) && (! is_string($config[$field]) || trim($config[$field]) === '')) {
                    $errors[] = "{$at}.{$field}: must be a non-empty string";
                }
            }

            if (array_key_exists('push_to_deploy', is_array($config) ? $config : []) && ! is_bool($config['push_to_deploy'])) {
                $errors[] = "{$at}.push_to_deploy: must be true or false";
            }

            $domains = $config['domains'] ?? [];

            if (! is_array($domains) || ! array_is_list($domains) || array_filter($domains, fn ($d) => ! is_string($d) || $d === '') !== []) {
                $errors[] = "{$at}.domains: must be a list of domain names";
            }
            self::variables($config['variables'] ?? [], "{$at}.variables", $errors);

            if (isset($config['database'])) {
                if (! is_string($config['database'])) {
                    $errors[] = "{$at}.database: must be the name of an entry in databases";
                } elseif (! isset($m['databases'][$config['database']])) {
                    $errors[] = "{$at}.database: no database named {$config['database']} in databases";
                }
            }

            self::required($m, $kind, $errors);
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private static function branch(mixed $branch, string $at, array &$errors): void
    {
        if (! is_array($branch) || ! isset($branch['rule'])) {
            $errors[] = "{$at}: missing";

            return;
        }

        if ($branch['rule'] === 'fixed') {
            if (! isset($branch['name']) || ! is_string($branch['name']) || trim($branch['name']) === '') {
                $errors[] = "{$at}.name: missing for rule fixed";
            }
        } elseif ($branch['rule'] !== 'slug') {
            $errors[] = "{$at}.rule: must be slug or fixed";
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private static function variables(mixed $variables, string $at, array &$errors): void
    {
        if (! is_array($variables)) {
            $errors[] = "{$at}: must be an object";

            return;
        }

        foreach ($variables as $key => $value) {
            self::key((string) $key, "{$at}.{$key}", $errors);

            if (! is_string($value)) {
                $errors[] = "{$at}.{$key}: value must be a string";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $m
     * @param  list<string>  $errors
     */
    private static function required(array $m, string $kind, array &$errors): void
    {
        $resolved = Manifest::variablesFor($m, $kind);
        $required = Manifest::requiredFor($m, $kind);

        if (! is_array($required)) {
            $errors[] = "kinds.{$kind}.required: must be an object";

            return;
        }

        foreach ($required as $key => $rule) {
            $at = "kinds.{$kind}.required.{$key}";
            self::key((string) $key, $at, $errors);

            if (! is_array($rule) || (isset($rule['equals']) && ! is_string($rule['equals'])) || (isset($rule['not']) && ! is_string($rule['not']))) {
                $errors[] = "{$at}: rule must be equals or not, with a string value";
            } elseif (isset($rule['equals'])) {
                if (($resolved[$key] ?? null) !== $rule['equals']) {
                    $errors[] = "{$at}: kind {$kind} must declare {$key} = {$rule['equals']}";
                }
            } elseif (isset($rule['not'])) {
                if (($resolved[$key] ?? null) === $rule['not']) {
                    $errors[] = "{$at}: kind {$kind} must not declare {$key} = {$rule['not']}";
                }
            } else {
                $errors[] = "{$at}: rule must be equals or not";
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private static function secrets(mixed $secrets, array &$errors, array $variables = []): void
    {
        $seen = [];
        $pairs = [];

        if (! is_array($secrets) || ! array_is_list($secrets)) {
            $errors[] = 'secrets: must be a list';

            return;
        }

        foreach ($secrets as $i => $secret) {
            $at = "secrets[{$i}]";
            $secret = is_array($secret) ? $secret : [];

            self::key(is_string($secret['key'] ?? null) ? $secret['key'] : '', "{$at}.key", $errors);

            // The ID is written by `secrets`; until then it is absent.
            if (array_key_exists('id', $secret) && (! is_string($secret['id']) || trim($secret['id']) === '')) {
                $errors[] = "{$at}.id: must be a secret ID, or left out until `secrets` writes it";
            }

            if (! in_array($secret['class'] ?? null, ['test', 'live'], true)) {
                $errors[] = "{$at}.class: must be test or live";
            }

            $allowed = $secret['kinds'] ?? null;

            if (! is_array($allowed) || $allowed === []) {
                $errors[] = "{$at}.kinds: missing (the environment kinds this secret may attach to)";

                continue;
            }

            foreach ($allowed as $kind) {
                if (! in_array($kind, Manifest::KINDS, true)) {
                    $errors[] = "{$at}.kinds: unknown environment kind ".json_encode($kind);
                } elseif (($secret['class'] ?? null) === 'live' && $kind !== 'production') {
                    $errors[] = "{$at}.kinds: a live secret may attach only to production, not {$kind}";
                } elseif (($secret['class'] ?? null) === 'test' && $kind === 'production') {
                    $errors[] = "{$at}.kinds: a test secret may not attach to production";
                } elseif (is_string($secret['key'] ?? null)) {
                    $taken = "{$secret['key']}@{$kind}";

                    if (isset($seen[$taken])) {
                        $errors[] = "{$at}.key: {$secret['key']} is already attached to {$kind} by {$seen[$taken]}";
                    }

                    $seen[$taken] = $at;
                }
            }

            if (is_string($secret['key'] ?? null) && is_string($secret['class'] ?? null)) {
                $pair = "{$secret['key']}:{$secret['class']}";

                if (isset($pairs[$pair])) {
                    $errors[] = "{$at}: {$secret['key']} already has a {$secret['class']} secret ({$pairs[$pair]}); list all its kinds on that one entry";
                }

                $pairs[$pair] = $at;
            }

            if (is_string($secret['key'] ?? null) && isset($variables) && array_key_exists($secret['key'], $variables)) {
                $errors[] = "{$at}.key: {$secret['key']} is also declared as a variable";
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private static function key(string $key, string $at, array &$errors): void
    {
        if (preg_match(self::NAME, $key) !== 1) {
            $errors[] = "{$at}: not a valid variable name";
        } elseif (InjectedKeys::isInjected($key)) {
            $errors[] = "{$at}: {$key} is injected or managed by Cloud and cannot be declared";
        }
    }
}
