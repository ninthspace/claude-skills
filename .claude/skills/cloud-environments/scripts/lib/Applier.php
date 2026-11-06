<?php

declare(strict_types=1);

namespace CloudEnvironments;

use Closure;

/**
 * Runs an approved plan. Before any call it checks the hash, refuses delete steps, any command
 * a plan never emits and any secret the manifest does not allow for the plan's kind, and checks
 * each variable still has the value the plan's digest was made from. Before any write it
 * re-reads Cloud and derives the plan again: a projected field that has changed since the plan
 * was made, or a step that differs from the derived one, refuses the run. Then it runs the steps in order, resolving
 * `{step:N.path}` from earlier steps' JSON and waiting for each created cluster, schema and
 * environment to be ready, and stops at the first failure.
 */
final class Applier
{
    private const POLL_SECONDS = 10;

    /** The commands a plan's create and change steps emit; apply runs no other. */
    private const COMMANDS = [
        'application:create', 'environment:create', 'environment:update', 'instance:update', 'database-cluster:create',
        'database-cluster:update', 'database:create', 'managed-queue:create', 'environment:variables', 'environment-secret:attach', 'domain:create',
    ];

    private Closure $sleep;

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(
        private readonly Cloud $writer,
        private readonly Cloud $reader,
        private readonly array $manifest,
        private readonly int $timeoutSeconds = 900,
        ?Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static fn (int $seconds) => sleep($seconds);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>  one line per step
     */
    public function apply(array $plan, string $givenHash): array
    {
        $computed = PlanFile::hash($plan);

        if (! hash_equals($computed, $givenHash) || ! hash_equals($computed, (string) ($plan['hash'] ?? ''))) {
            throw new ApplyRefused('the plan does not match the approved hash; make and approve a new plan');
        }

        if (($plan['application'] ?? null) !== $this->manifest['application'] || ($plan['organisation'] ?? null) !== $this->manifest['organisation']) {
            throw new ApplyRefused("the plan is for {$plan['application']} in {$plan['organisation']}, but the manifest is for {$this->manifest['application']} in {$this->manifest['organisation']}");
        }

        $kind = $plan['kind'];
        $values = Manifest::variablesFor($this->manifest, $kind);
        $allowed = [];

        foreach ($this->manifest['secrets'] ?? [] as $secret) {
            if (in_array($kind, $secret['kinds'], true) && isset($secret['id'])) {
                $allowed[] = $secret['id'];
            }
        }

        foreach ($plan['steps'] as $step) {
            if ($step['action'] === 'delete') {
                throw new ApplyRefused("the plan holds a delete step ({$step['n']}, {$step['note']}); deletes go through `remove`");
            }

            if ($step['command'] === null) {
                continue;
            }

            if ($step['command'] === 'secret:create') {
                throw new ApplyRefused("step {$step['n']} creates a secret; secrets are created only by `secrets`");
            }

            if (! in_array($step['command'], self::COMMANDS, true)) {
                throw new ApplyRefused("step {$step['n']} runs {$step['command']}, which a plan never emits");
            }

            if ($step['command'] === 'environment-secret:attach') {
                foreach (array_slice($step['args'], 1) as $id) {
                    if (! in_array($id, $allowed, true)) {
                        throw new ApplyRefused("step {$step['n']} attaches secret {$id}, which the manifest does not allow on a {$kind}");
                    }
                }
            }

            if ($step['resource'] === 'variable') {
                $key = substr($step['key'], strlen('variable:'));

                if (! array_key_exists($key, $values) || hash('sha256', $values[$key]) !== ($step['digest'] ?? null)) {
                    throw new ApplyRefused("{$key} in the manifest has changed since the plan was made; make and approve a new plan");
                }
            }
        }

        $slug = $kind === 'preview' ? substr($plan['environment'], strlen('preview-')) : null;
        $fresh = (new Planner($this->reader, $this->manifest))->plan($kind, $slug, $kind === 'preview' ? $plan['branch'] : null);

        if (PlanFile::canonical($fresh['state']) !== PlanFile::canonical($plan['state'])) {
            $changed = array_keys(array_filter($plan['state'], fn ($value, $key) => PlanFile::canonical($value) !== PlanFile::canonical($fresh['state'][$key] ?? null), ARRAY_FILTER_USE_BOTH));

            throw new ApplyRefused('Cloud has changed since the plan was made ('.implode(', ', $changed).'); make and approve a new plan');
        }

        // Run only what plan itself derives: a plan file edited or written by hand before it was
        // rendered could otherwise carry a command under a matching hash.
        if (PlanFile::canonical($fresh['steps']) !== PlanFile::canonical($plan['steps'])) {
            throw new ApplyRefused('the plan\'s steps are not the ones plan derives from the manifest and Cloud; make and approve a new plan');
        }

        $outputs = [];
        $log = [];

        foreach ($plan['steps'] as $step) {
            if ($step['command'] === null) {
                $log[] = "{$step['n']} {$step['action']} {$step['resource']}: nothing to do";

                continue;
            }

            try {
                $args = array_map(fn (string $arg) => $this->resolve($arg, $outputs, $values), $step['args']);
                $outputs[$step['n']] = $this->writer->json(array_merge([$step['command']], $args));
                $outputs[$step['n']] = $this->waitUntilReady($step['command'], $outputs[$step['n']], $args);
            } catch (\RuntimeException $e) {
                if ($step['command'] === 'application:create' && str_contains($e->getMessage(), 'could not be found')) {
                    $e = new \RuntimeException($e->getMessage().' — give Laravel Cloud access to the repository: in GitHub, the organisation\'s Settings → GitHub Apps → Laravel Cloud → Configure → Repository access, add the repository; then apply again');
                }

                throw new ApplyFailed(implode("\n", array_merge($log, ["{$step['n']} {$step['action']} {$step['resource']}: FAILED — {$e->getMessage()}", 'No later step ran. Make a new plan: the steps that ran will show as keep.'])));
            }

            $log[] = "{$step['n']} {$step['action']} {$step['resource']}: done";
        }

        return $log;
    }

    /**
     * @param  array<int, mixed>  $outputs
     * @param  array<string, string>  $values
     */
    private function resolve(string $arg, array $outputs, array $values): string
    {
        return (string) preg_replace_callback('/\{(step):(\d+)\.([A-Za-z0-9_.]+)\}|\{(var):([A-Z][A-Z0-9_]*)\}/', function (array $m) use ($outputs, $values): string {
            if ($m[1] === 'step') {
                $value = $outputs[(int) $m[2]] ?? null;

                foreach (explode('.', $m[3]) as $part) {
                    $value = is_array($value) ? ($value[$part] ?? null) : null;
                }

                if (! is_string($value) && ! is_int($value)) {
                    throw new ApplyFailed("{$m[0]} has no value: step {$m[2]} returned no {$m[3]}");
                }

                return (string) $value;
            }

            return $values[$m[5]];
        }, $arg);
    }

    /**
     * Waits for a created cluster, schema or environment to report ready, and returns what
     * later steps should resolve references from.
     *
     * @param  list<string>  $args
     */
    private function waitUntilReady(string $command, mixed $output, array $args): mixed
    {
        $id = is_array($output) ? ($output['id'] ?? null) : null;

        $check = match ($command) {
            'database-cluster:create' => fn () => $this->clusterReady($id) ? $output : null,
            'database:create' => fn () => $this->schemaPresent($id) ? $output : null,
            'environment:create' => function () use ($id) {
                $env = $this->reader->object(['environment:get', (string) $id]);

                return ($env['instances'] ?? []) !== [] ? $env : null;
            },
            default => null,
        };

        if ($check === null) {
            return $output;
        }

        if ($id === null) {
            throw new ApplyFailed("{$command} returned no id");
        }

        for ($waited = 0; ; $waited += self::POLL_SECONDS) {
            $ready = $check();

            if ($ready !== null) {
                return $ready;
            }

            if ($waited >= $this->timeoutSeconds) {
                throw new ApplyFailed("{$command} {$id} was not ready after {$this->timeoutSeconds} seconds");
            }

            ($this->sleep)(self::POLL_SECONDS);
        }
    }

    private function clusterReady(string $id): bool
    {
        foreach ($this->reader->list(['database-cluster:list']) as $cluster) {
            if ($cluster['id'] === $id) {
                return $cluster['status'] === 'available';
            }
        }

        return false;
    }

    private function schemaPresent(string $id): bool
    {
        foreach ($this->reader->list(['database-cluster:list']) as $cluster) {
            if (in_array($id, array_column($cluster['schemas'] ?? [], 'id'), true)) {
                return true;
            }
        }

        return false;
    }
}
