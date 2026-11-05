<?php

declare(strict_types=1);

namespace CloudEnvironments;

/**
 * Draws one or more plan files as a single self-contained HTML page for approval. It reads
 * only the plans (and the rates table it is given): it makes no Cloud call.
 */
final class Renderer
{
    /**
     * @param  list<array<string, mixed>>  $plans
     * @param  array<string, mixed>  $rates
     */
    public static function render(array $plans, array $rates): string
    {
        $warnings = [];
        $sections = [];
        $clusters = [];
        $totalCents = 0;
        $complete = true;

        foreach ($plans as $plan) {
            [$html, $planWarnings, $cents, $priced] = self::environment($plan, $rates, $clusters);
            $complete = $complete && $priced;
            $sections[] = $html;
            $warnings = array_merge($warnings, $planWarnings);
            $totalCents += $cents;
        }

        $warningHtml = $warnings === [] ? '' : '<section class="warnings" role="alert"><h2>Check before approving</h2><ul>'
            .implode('', array_map(fn ($w) => '<li data-warning="1">'.self::e($w).'</li>', $warnings)).'</ul></section>';

        $names = implode(', ', array_map(fn ($p) => $p['environment'], $plans));

        $title = self::e(count($plans) === 1 ? $plans[0]['environment'].' plan' : 'Environment plan diagram');

        return self::page(
            $warningHtml
            .'<p class="lede">Environments in this proposal: '.self::e($names).'. Estimated monthly cost for all of them: <strong data-total-cents="'.$totalCents.'">'.self::money($totalCents).'</strong>'.($complete ? '' : ' <span class="est" data-incomplete="1">incomplete: some lines have no rate</span>').'.</p>'
            .implode('', $sections)
            .'<p class="foot">Rates read '.self::e((string) $rates['date']).'. '.self::e((string) $rates['source']).'. '.self::e((string) $rates['note']).'</p>',
            $title,
        );
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $rates
     * @param  array<string, true>  $clusters  clusters already counted on this page
     * @return array{0: string, 1: list<string>, 2: int, 3: bool}
     */
    private static function environment(array $plan, array $rates, array &$clusters): array
    {
        $env = $plan['environment'];
        $warnings = [];
        $catalog = array_column($plan['secrets'] ?? [], null, 'id');

        $attached = $plan['state']['secrets'] ?? [];

        foreach ($plan['steps'] as $step) {
            if ($step['resource'] === 'secret' && $step['command'] !== null) {
                $attached[] = $step['args'][1] ?? '';
            }
        }

        $attached = array_values(array_unique($attached));
        $chips = '';
        $kindsText = '';

        foreach ($attached as $id) {
            $secret = $catalog[$id] ?? null;
            $problem = null;

            if ($secret === null) {
                $problem = "secret {$id} is attached to {$env} but is not in the manifest";
            } elseif (! in_array($plan['kind'], $secret['kinds'], true)) {
                $problem = "{$secret['key']} ({$secret['class']}, {$id}) is not allowed on a {$plan['kind']}; it may attach to ".implode(', ', $secret['kinds']);
            } elseif (($secret['class'] === 'live') === ($plan['kind'] !== 'production')) {
                $problem = "{$secret['key']} is a {$secret['class']} secret and {$env} is a {$plan['kind']}";
            }

            if ($problem !== null) {
                $warnings[] = $problem;
            }

            $chips .= '<li class="secret'.($problem !== null ? ' warn' : '').'" data-secret="'.self::e($id).'" data-environment="'.self::e($env).'" data-class="'.self::e($secret['class'] ?? 'unknown').'">'
                .'<span class="key">'.self::e($secret['key'] ?? $id).'</span> <span class="meta">'.self::e($secret['class'] ?? 'unknown').' · allowed on '.self::e(implode(', ', $secret['kinds'] ?? [])).'</span>'
                .($problem !== null ? ' <strong class="flag">outside its allowed kinds</strong>' : '').'</li>';
        }

        $rows = '';
        $variables = [];

        foreach ($plan['steps'] as $step) {
            $rows .= '<tr data-step="'.(int) $step['n'].'" data-action="'.self::e($step['action']).'"><td>'.(int) $step['n'].'</td><td><span class="action a-'.self::e($step['action']).'">'.self::e($step['action']).'</span></td><td>'
                .self::e($step['resource']).'</td><td>'.self::e($step['note']).'</td></tr>';

            if ($step['resource'] === 'variable') {
                $variables[] = substr($step['key'], strlen('variable:'));
            }
        }

        $variableHtml = implode('', array_map(fn ($k) => '<li data-variable="'.self::e($k).'">'.self::e($k).'</li>', $variables));

        [$costRows, $cents, $priced] = self::costs($plan, $rates, $clusters);

        $cards = self::cards($plan);
        $html = '<section class="env" data-environment="'.self::e($env).'" data-kind="'.self::e($plan['kind']).'">'
            .'<h2>'.self::e($env).' <span class="kind">'.self::e($plan['kind']).' · branch '.self::e($plan['branch']).'</span></h2>'
            .'<p class="meta">Plan <code class="plan-code" data-plan-code="'.self::e(substr((string) ($plan['hash'] ?? ''), 0, 8)).'" data-hash="'.self::e((string) ($plan['hash'] ?? '')).'">'.self::e(isset($plan['hash']) ? substr((string) $plan['hash'], 0, 8) : 'not written yet').'</code></p>'
            .$cards
            .'<details class="steps"><summary><h3>Steps</h3> <span class="muted">'.count($plan['steps']).' steps, in the order apply runs them</span><span class="show">Show</span></summary><div class="scroll"><table><thead><tr><th>#</th><th>Action</th><th>Resource</th><th>Detail</th></tr></thead><tbody>'.$rows.'</tbody></table></div></details>'
            .'<h3>Variables (names only)</h3><ul class="chips">'.$variableHtml.'</ul>'
            .'<div class="band"><h3>Organisation-level secrets</h3><ul class="secrets">'.($chips === '' ? '<li class="meta">none attached</li>' : $chips).'</ul></div>'
            .'<h3>Estimated monthly cost</h3><table><tbody>'.$costRows.'<tr class="total"><td>Total'.($priced ? '' : ' <span class="est" data-incomplete="1">incomplete</span>').'</td><td data-cost-cents="'.$cents.'">'.self::money($cents).'</td></tr></tbody></table>'
            .'</section>';

        return [$html, $warnings, $cents, $priced];
    }

    /**
     * The environment as the Cloud dashboard draws it: a dotted canvas with side-by-side
     * columns (Network, then the region's compute, then the region's database) joined by
     * lines, cards with an icon on every row, status dots for on/off settings, and Cloud's
     * size names. Each card is marked with what the plan does to it. A resource the plan
     * does not declare has no card.
     *
     * @param  array<string, mixed>  $plan
     */
    private static function cards(array $plan): string
    {
        $res = $plan['resources'] ?? [];
        $actions = [];

        foreach ($plan['steps'] as $step) {
            $actions[$step['key']] = $step['action'];
        }

        $mark = static function (array $keys) use ($actions): string {
            $found = array_values(array_intersect_key($actions, array_flip($keys)));

            foreach (['create', 'change', 'delete'] as $action) {
                if (in_array($action, $found, true)) {
                    return $action === 'delete' ? 'change' : $action;
                }
            }

            return 'keep';
        };
        $domainKeys = array_values(array_filter(array_keys($actions), fn ($k) => str_starts_with($k, 'domain:')));
        $custom = array_map(fn ($k) => substr($k, strlen('domain:')), array_values(array_filter($domainKeys, fn ($k) => $actions[$k] !== 'delete')));

        // A kind that declares no domains has no domain steps; its existing domains are kept.
        foreach ($plan['state']['domains'] ?? [] as $domain) {
            if (! in_array($domain['name'], $custom, true) && ($actions['domain:'.$domain['name']] ?? 'keep') !== 'delete') {
                $custom[] = $domain['name'];
            }
        }

        $label = self::e(self::regionLabel((string) ($plan['region'] ?? '')));
        $columns = [];

        $columns[] = '<div class="col" data-group="network"><h4>Network</h4>'
            .self::card('Edge network', 'network', null, 'keep', [
                ['shield', 'DDoS protection', self::status(true)],
                ['globe', 'CDN', self::status(true)],
                ['bolt', 'Edge caching', self::status(true)],
            ])
            .self::card('Domains', 'globe', null, $domainKeys === [] ? 'keep' : $mark($domainKeys), array_merge(
                [['globe', 'Cloud domain', self::status(true).'<span class="sub">assigned by Cloud</span>']],
                $custom === [] ? [['globe', 'Custom domains', '<span class="muted">none</span>']] : array_map(fn ($d) => ['globe', 'Custom domain', self::e($d)], $custom),
            ))
            .'</div>';

        $compute = '';

        if (isset($res['instance'])) {
            $i = $res['instance'];
            $compute .= self::card('App cluster', 'server', $plan['environment'], $mark(['environment', 'environment-settings', 'instance']), [
                ['chip', 'Compute', self::size((string) $i['size'])],
                ['scale', 'Autoscaling', '<span class="muted">set in the dashboard</span>'],
                ['moon', 'Scale to zero', ($i['scale_to_zero'] ?? false) ? self::status(true, isset($i['sleep_timeout']) ? 'After '.$i['sleep_timeout'].' minutes' : 'Enabled') : self::status(false)],
                ['calendar', 'Scheduler', self::status((bool) ($i['scheduler'] ?? false))],
                ['branch', 'Branch', self::e($plan['branch'])],
                ['bolt', 'Push to deploy', isset($res['push_to_deploy']) ? self::status((bool) $res['push_to_deploy']) : '<span class="muted">set in the dashboard</span>'],
            ]);
        }

        if (isset($res['queue'])) {
            $q = $res['queue'];
            $compute .= self::card('Managed queue', 'queue', (string) ($q['name'] ?? 'default'), $mark(['queue', 'queue-scaling']), [
                ['chip', 'Memory', self::size((string) $q['size'])],
                ['scale', 'Autoscaling', isset($q['min_workers']) ? (int) $q['min_workers'].'–'.(int) $q['max_workers'].' workers' : 'up to '.(int) $q['max_workers'].' workers'],
            ]);
        }

        if ($compute !== '') {
            $columns[] = '<div class="col" data-group="region"><h4>'.$label.'</h4>'.$compute.'</div>';
        }

        if (isset($res['database'])) {
            $d = $res['database'];
            $columns[] = '<div class="col" data-group="region"><h4>'.$label.'</h4>'
                .self::card('Database', 'table', $plan['environment'], $mark(['cluster', 'cluster-settings', 'schema']), [
                    ['grid', 'Type', self::e(self::DATABASE_TYPES[$d['type']] ?? $d['type'])],
                    ['chip', 'Compute', isset($d['size']) ? self::size((string) $d['size']) : '<span class="muted">set in the dashboard</span>'],
                    ['disk', 'Storage', isset($d['storage']) ? self::e((string) $d['storage']).' GiB' : '<span class="muted">set in the dashboard</span>'],
                    ['moon', 'Scale to zero', isset($d['scale_to_zero_after']) ? ($d['scale_to_zero_after'] > 0 ? self::status(true, 'After '.intdiv((int) $d['scale_to_zero_after'], 60).' minutes') : self::status(false)) : '<span class="muted">set in the dashboard</span>'],
                    ['cylinder', 'Cluster', self::e($d['name']).($mark(['cluster']) === 'create' ? ' <span class="muted">(new)</span>' : '')],
                    ['table', 'Schema', self::e($plan['environment'])],
                ])
                .'</div>';
        }

        return '<div class="canvas">'.implode('', $columns).'</div>';
    }

    private const DATABASE_TYPES = ['laravel_mysql' => 'MySQL', 'neon_serverless_postgres' => 'Serverless Postgres'];

    /**
     * A size as Cloud writes it: "Flex 1 GiB · 1 vCPU", "Flex 256 MiB", "Pro 8 GiB · 2 vCPU".
     * A size it does not recognise is shown as given.
     */
    private static function size(string $size): string
    {
        $memory = static fn (string $amount, string $unit): string => $unit === 'mb' ? "{$amount} MiB" : "{$amount} GiB";

        if (preg_match('/^(?:db-|mysql-)?flex(?:\.m-1vcpu)?-(\d+)(mb|gb)$/', $size, $m) === 1) {
            return '<strong>Flex</strong> <span class="muted">'.$memory($m[1], $m[2]).' · 1 vCPU</span>';
        }

        if (preg_match('/^mysql-pro-(\d+)gb$/', $size, $m) === 1) {
            return '<strong>Pro</strong> <span class="muted">'.$m[1].' GiB</span>';
        }

        if (preg_match('/^pro\.m-(\d+)vcpu-(\d+)gb$/', $size, $m) === 1) {
            return '<strong>Pro</strong> <span class="muted">'.$m[2].' GiB · '.$m[1].' vCPU</span>';
        }

        if (preg_match('/^mq[.-](flex|pro|dedicated)[.-](?:(?:flex|pro)[.-])?(\d+)(mb|gb)$/', $size, $m) === 1) {
            return '<strong>'.ucfirst($m[1]).'</strong> <span class="muted">'.$memory($m[2], $m[3]).'</span>';
        }

        return self::e($size);
    }

    private static function status(bool $on, ?string $word = null): string
    {
        return '<span class="dot '.($on ? 'on' : 'off').'" aria-hidden="true"></span><span class="'.($on ? 'on-text' : 'muted').'">'.self::e($word ?? ($on ? 'Enabled' : 'Disabled')).'</span>';
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows  icon, label, value HTML
     */
    private static function card(string $title, string $icon, ?string $subtitle, string $action, array $rows): string
    {
        $body = implode('', array_map(fn ($r) => '<div class="row" data-row="'.self::e($r[1]).'">'.self::icon($r[0]).'<span class="label">'.self::e($r[1]).'</span><span class="v">'.$r[2].'</span></div>', $rows));

        return '<div class="card" data-card="'.self::e($title).'" data-action="'.self::e($action).'">'
            .'<div class="card-head">'.self::icon($icon, 'head-icon').'<strong>'.self::e($title).'</strong>'.($subtitle !== null ? ' <span class="muted">'.self::e($subtitle).'</span>' : '')
            .' <span class="action a-'.self::e($action).'">'.self::e($action).'</span></div>'.$body.'</div>';
    }

    /**
     * A 16px stroke icon drawn in the text colour.
     */
    private static function icon(string $name, string $class = 'icon'): string
    {
        $paths = [
            'network' => '<circle cx="8" cy="8" r="2"/><circle cx="8" cy="2.5" r="1.2"/><circle cx="8" cy="13.5" r="1.2"/><circle cx="2.5" cy="8" r="1.2"/><circle cx="13.5" cy="8" r="1.2"/><path d="M8 4v2M8 10v2M4 8h2M10 8h2"/>',
            'shield' => '<path d="M8 1.5 13 3.5v4c0 3-2.2 5.4-5 6.5-2.8-1.1-5-3.5-5-6.5v-4z"/>',
            'globe' => '<circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c1.8 1.8 2.6 3.8 2.6 6S9.8 12.2 8 14C6.2 12.2 5.4 10.2 5.4 8S6.2 3.8 8 2z"/>',
            'bolt' => '<path d="M9 1.5 3.5 9H8l-1 5.5L12.5 7H8z"/>',
            'server' => '<rect x="2" y="3" width="12" height="4" rx="1"/><rect x="2" y="9" width="12" height="4" rx="1"/><path d="M4.5 5h.01M4.5 11h.01"/>',
            'chip' => '<rect x="2" y="3.5" width="12" height="9" rx="1.5"/><path d="M2 7h12"/>',
            'scale' => '<rect x="2" y="5" width="7" height="7" rx="1"/><path d="M11 3h2.5v2.5M13.5 3 10 6.5"/>',
            'moon' => '<path d="M12.5 10A5.5 5.5 0 0 1 6 3.5a5.5 5.5 0 1 0 6.5 6.5z"/>',
            'calendar' => '<rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 7h11M5.5 2v3M10.5 2v3"/>',
            'branch' => '<circle cx="4.5" cy="3.5" r="1.5"/><circle cx="4.5" cy="12.5" r="1.5"/><circle cx="11.5" cy="5.5" r="1.5"/><path d="M4.5 5v6M11.5 7c0 3-7 2-7 4"/>',
            'queue' => '<rect x="1.5" y="3" width="13" height="10" rx="1.5"/><path d="M4.5 6.5 6.5 8l-2 1.5M8 10h3"/>',
            'table' => '<rect x="2" y="2.5" width="12" height="11" rx="1.5"/><path d="M2 6.5h12M6 6.5v7"/>',
            'grid' => '<rect x="2.5" y="2.5" width="4" height="4" rx="1"/><rect x="9.5" y="2.5" width="4" height="4" rx="1"/><rect x="2.5" y="9.5" width="4" height="4" rx="1"/><rect x="9.5" y="9.5" width="4" height="4" rx="1"/>',
            'disk' => '<rect x="2.5" y="2" width="11" height="12" rx="1.5"/><path d="M2.5 10h11M5 12h.01"/>',
            'cylinder' => '<ellipse cx="8" cy="3.5" rx="5" ry="1.8"/><path d="M3 3.5v9c0 1 2.2 1.8 5 1.8s5-.8 5-1.8v-9M3 8c0 1 2.2 1.8 5 1.8S13 9 13 8"/>',
        ];

        return '<svg class="'.$class.'" viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    }

    private static function regionLabel(string $region): string
    {
        return ['eu-west-2' => 'EU West (London)', 'eu-central-1' => 'EU Central (Frankfurt)', 'us-east-1' => 'US East (N. Virginia)', 'us-east-2' => 'US East (Ohio)'][$region] ?? ($region === '' ? 'Region' : $region);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $rates
     * @param  array<string, true>  $clusters
     * @return array{0: string, 1: int, 2: bool}
     */
    private static function costs(array $plan, array $rates, array &$clusters): array
    {
        $res = $plan['resources'] ?? [];
        $lines = [];

        if (isset($res['instance'])) {
            $lines[] = ['App instance '.$res['instance']['size'], $rates['lines']['instance'][$res['instance']['size']] ?? null, 1];
        }

        if (isset($res['queue'])) {
            $lines[] = ['Managed queue worker '.$res['queue']['size'].' (one worker)', $rates['lines']['queue_worker'][$res['queue']['size']] ?? null, 1];
        }

        if (isset($res['database']) && ! isset($clusters[$res['database']['name']])) {
            $clusters[$res['database']['name']] = true;
            $lines[] = ['Database cluster '.$res['database']['name'].' (shared; counted once)', $rates['lines']['database'][$res['database']['type']] ?? null, 1];
        }

        $rows = '';
        $cents = 0;
        $priced = true;

        foreach ($lines as [$label, $rate, $count]) {
            if ($rate === null) {
                $rows .= '<tr><td>'.self::e($label).'</td><td class="est">no rate in the table</td></tr>';
                $priced = false;

                continue;
            }

            $cents += $rate['monthly_cents'] * $count;
            $rows .= '<tr><td>'.self::e($label).'</td><td data-cost-cents="'.(int) ($rate['monthly_cents'] * $count).'">'.self::money($rate['monthly_cents'] * $count)
                .($rate['estimate'] ? ' <span class="est">estimate</span>' : '').'</td></tr>';
        }

        return [$rows, $cents, $priced];
    }

    private static function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function page(string $body, string $title): string
    {
        $light = '--bg:#f7f8fa;--surface:#ffffff;--text:#1b2230;--muted:#5b6578;--line:#d9dee7;--create:#1f7a3d;--change:#9a6200;--keep:#5b6578;--delete:#b3261e;--warn-bg:#fdecea;--warn:#b3261e;--accent:#2457c5;--group:#eef0f4;--canvas:#f7f8fa;--dots:#d4d8e0;--connector:#c3c8d2;--shadow:rgba(20,30,50,.06);';
        $dark = '--bg:#12151c;--surface:#1b2029;--text:#e6e9ef;--muted:#9aa4b5;--line:#2d3544;--create:#5fd38a;--change:#f0b44a;--keep:#9aa4b5;--delete:#ff8a80;--warn-bg:#3a1f1d;--warn:#ff8a80;--accent:#8ab0ff;--group:#161a22;--canvas:#12151c;--dots:#2a303c;--connector:#3a4252;--shadow:rgba(0,0,0,.3);';

        // The artifact host wraps the page in its own document skeleton, so the page starts
        // with its title and style and carries no html, head or body tags.
        return <<<HTML
<title>{$title}</title>
<meta name="claude:viewport-fit-sides" content="cover">
<style>
:root { {$light} }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { {$dark} color-scheme: dark; } }
:root[data-theme="dark"] { {$dark} color-scheme: dark; }
* { box-sizing: border-box; }
body { margin: 0; padding-block: 24px 48px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, sans-serif; }
main { max-width: 1360px; margin: 0 auto; }
h1 { font-size: 1.5rem; margin: 0 0 8px; }
h2 { font-size: 1.15rem; margin: 0 0 12px; }
h3 { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 20px 0 8px; }
.kind, .meta, .foot, .lede { color: var(--muted); }
.kind { font-weight: 400; font-size: .85rem; }
.env { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin: 16px 0; }
.warnings { background: var(--warn-bg); border: 1px solid var(--warn); color: var(--warn); border-radius: 10px; padding: 12px 16px; margin: 16px 0; }
.canvas { display: grid; grid-template-columns: minmax(0, 1fr); gap: 32px; padding: 24px; border: 1px solid var(--line); border-radius: 14px; background-color: var(--canvas); background-image: radial-gradient(var(--dots) 1px, transparent 1px); background-size: 16px 16px; }
.col { position: relative; display: flex; flex-direction: column; gap: 14px; padding: 14px; border-radius: 14px; background: var(--group); min-width: 0; }
.col + .col::before { content: ""; position: absolute; left: -33px; top: 74px; width: 33px; border-top: 1px solid var(--connector); }
.col h4 { margin: 0 0 2px; font-size: .9rem; font-weight: 500; color: var(--muted); }
.card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 1px 2px var(--shadow); }
.card-head { padding: 12px 14px; border-bottom: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 1rem; }
.card-head .action { margin-left: auto; font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; padding: 2px 8px; border-radius: 999px; border: 1px solid currentColor; }
.head-icon { width: 18px; height: 18px; }
.row { display: grid; grid-template-columns: 16px auto 1fr; align-items: center; gap: 10px; padding: 8px 14px; font-size: .9rem; }
.row .icon { color: var(--muted); }
.row .label { color: var(--muted); }
.row .v { text-align: right; overflow-wrap: anywhere; }
.row .sub { display: block; font-size: .8rem; color: var(--muted); }
.dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
.dot.on { background: var(--create); }
.dot.off { background: var(--muted); }
.on-text { color: var(--create); }
.muted { color: var(--muted); font-weight: 400; }
details.steps { margin-top: 20px; }
details.steps summary { cursor: pointer; }
details.steps summary .show { color: var(--accent); margin-left: 8px; }
details.steps[open] summary .show { display: none; }
details.steps summary h3 { display: inline; margin: 0; }
.chips li { overflow-wrap: anywhere; }
@media (min-width: 1000px) { .canvas { grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); align-items: start; } }
@media (max-width: 999px) { .col + .col::before { display: none; } }
@media (max-width: 700px) { .canvas { padding: 12px; gap: 16px; } }
.plan-code { font-size: 1rem; font-weight: 600; letter-spacing: .04em; }
section.env > table, .scroll { display: block; overflow-x: auto; }
table { border-collapse: collapse; width: 100%; font-size: .9rem; }
th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
.action { font-weight: 600; }
.a-create { color: var(--create); } .a-change { color: var(--change); } .a-keep { color: var(--keep); } .a-delete { color: var(--delete); }
.chips, .secrets { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
.chips li, .secrets li { border: 1px solid var(--line); border-radius: 999px; padding: 2px 10px; background: var(--bg); font-size: .85rem; }
.band { border-top: 2px dashed var(--line); margin-top: 16px; }
.secret.warn { border-color: var(--warn); background: var(--warn-bg); color: var(--warn); }
.flag { font-size: .75rem; }
.est { font-size: .75rem; color: var(--muted); font-style: italic; }
.total td { font-weight: 600; }

</style>
<main>
<h1>{$title}</h1>
{$body}
</main>
HTML;
    }
}
