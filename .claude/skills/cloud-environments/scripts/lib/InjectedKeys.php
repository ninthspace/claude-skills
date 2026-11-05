<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * The variables Cloud injects or manages, read from references/injected-keys.json (the
 * one table). A manifest may not declare any of them.
 */
final class InjectedKeys
{
    /** @var array{exact: list<string>, prefixes: list<string>}|null */
    private static ?array $table = null;

    /**
     * @return array{exact: list<string>, prefixes: list<string>}
     */
    public static function table(): array
    {
        if (self::$table === null) {
            $table = json_decode((string) file_get_contents(__DIR__.'/../../references/injected-keys.json'), true, 512, JSON_THROW_ON_ERROR);
            self::$table = ['exact' => $table['exact'], 'prefixes' => $table['prefixes']];
        }

        return self::$table;
    }

    public static function isInjected(string $key): bool
    {
        $table = self::table();

        if (in_array($key, $table['exact'], true)) {
            return true;
        }

        foreach ($table['prefixes'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
