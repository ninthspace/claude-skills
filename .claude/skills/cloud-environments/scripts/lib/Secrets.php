<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Creates the Cloud secrets the manifest lists and writes their IDs into it. Each secret is
 * named by its key and tagged in its notes as `cloud-environments:{application}:{key}:{class}`,
 * so a later run finds it instead of creating another. Values are read from the terminal and
 * reach the CLI on standard input only.
 */
final class Secrets
{
    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(private readonly Cloud $reader, private readonly Cloud $writer, private array $manifest, private readonly string $manifestPath) {}

    public static function tag(string $application, string $key, string $class): string
    {
        return "cloud-environments:{$application}:{$key}:{$class}";
    }

    /**
     * @return list<string>
     */
    public function setup(Terminal $terminal, bool $fromStdin = false): array
    {
        if (! $terminal->isInteractive() && ! $fromStdin) {
            throw new PlanRefused('secrets reads values from a terminal; run it in your own terminal, or pass --from-stdin to pipe them in');
        }

        Organisation::check($this->reader->list(['application:list']), $this->manifest);
        $existing = $this->reader->list(['secret:list']);
        $ids = array_column($existing, 'id');
        $log = [];
        $changed = false;

        foreach ($this->manifest['secrets'] ?? [] as $i => $secret) {
            $label = "{$secret['key']} ({$secret['class']}, ".implode(', ', $secret['kinds']).')';
            $tagged = $this->tagged($existing, $secret);

            if (isset($secret['id']) && in_array($secret['id'], $ids, true)) {
                $log[] = "{$label}: present";

                continue;
            }

            if (count($tagged) > 1) {
                $log[] = "{$label}: DUPLICATE — ".count($tagged).' secrets carry its tag ('.implode(', ', array_column($tagged, 'id')).'); delete the extra ones, then run again';

                continue;
            }

            if (count($tagged) === 1) {
                $this->manifest['secrets'][$i]['id'] = $tagged[0]['id'];
                $changed = true;
                $log[] = "{$label}: found {$tagged[0]['id']}, written to the manifest";

                continue;
            }

            $value = $terminal->readValue("Value for {$label}: ");

            if ($value === null || $value === '') {
                $log[] = "{$label}: skipped (no value)";

                continue;
            }

            $created = $this->writer->json(['secret:create', '--name='.$secret['key'], '--notes='.self::tag($this->manifest['application'], $secret['key'], $secret['class'])], false, false, $value);
            unset($value);

            if (! is_array($created) || ! is_string($created['id'] ?? null)) {
                throw new CloudError("secret:create for {$secret['key']} returned no id");
            }

            $replaced = $secret['id'] ?? null;
            $this->manifest['secrets'][$i]['id'] = $created['id'];
            $changed = true;
            $this->save();
            $log[] = "{$label}: created {$created['id']}, written to the manifest".($replaced !== null ? " (replacing {$replaced}, which the organisation does not hold)" : '');
        }

        if ($changed) {
            $this->save();
        }

        return $log;
    }

    /**
     * @return list<string>  one line per manifest secret; a line not ending in "present" is a problem
     */
    public function status(): array
    {
        Organisation::check($this->reader->list(['application:list']), $this->manifest);
        $existing = $this->reader->list(['secret:list']);
        $ids = array_column($existing, 'id');
        $lines = [];

        foreach ($this->manifest['secrets'] ?? [] as $secret) {
            $label = "{$secret['key']} ({$secret['class']})";
            $tagged = $this->tagged($existing, $secret);

            if (count($tagged) > 1) {
                $lines[] = "{$label}: duplicated (".implode(', ', array_column($tagged, 'id')).')';
            } elseif (! isset($secret['id'])) {
                $lines[] = "{$label}: missing";
            } elseif (! in_array($secret['id'], $ids, true)) {
                $lines[] = "{$label}: unknown — {$secret['id']} is not in the organisation";
            } else {
                $lines[] = "{$label}: present";
            }
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  array<string, mixed>  $secret
     * @return list<array<string, mixed>>
     */
    private function tagged(array $existing, array $secret): array
    {
        $tag = self::tag($this->manifest['application'], $secret['key'], $secret['class']);

        return array_values(array_filter($existing, fn ($s) => ($s['notes'] ?? null) === $tag));
    }

    private function save(): void
    {
        // Two-space indentation, as a hand-written manifest usually has; written to a temporary
        // file and renamed, so an interrupted run never leaves half a manifest.
        $json = json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $json = (string) preg_replace_callback('/^( {4})+/m', fn ($m) => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json);
        $temporary = $this->manifestPath.'.tmp';
        file_put_contents($temporary, $json."\n");
        rename($temporary, $this->manifestPath);
    }
}
