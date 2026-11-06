# Repository scan

Run all of it before forming a view. Each block says what the result means for the
architecture, so a scan with no findings is still an answer.

Commands assume the application root. Adjust paths for a monorepo.

## Runtime and extensions

```bash
grep -E '"php"|"ext-' composer.json
grep -E 'laravel/framework|laravel/horizon|laravel/nova|laravel/octane|laravel/reverb|filament/' composer.json
grep -E 'flysystem-aws-s3|maatwebsite/excel|spatie/laravel-health|sentry|intervention/image' composer.json
```

- **PHP constraint** — pin the environment to the version the application is tested on. New
  environments default to the newest supported release.
- **`ext-*`** — check each against Cloud's published extension list. `soap`, `bcmath`,
  `imagick`, `gd`, `intl`, `ldap`, `imap`, `redis`, `sodium`, `mongodb` and the SQL Server
  drivers are all available; the list is in the Environments documentation. `sodium` is
  needed for the secrets workflow specifically.
- **Horizon** — disqualifies managed queues; see the queue table in `SKILL.md`.
- **Octane, Reverb** — Octane is a toggle on the environment; Reverb means a WebSocket
  cluster, which is a resource and a line item.
- **No S3 adapter** — it has to be added before object storage works.

## Queues

```bash
cat config/horizon.php | grep -E "'connection'|'queue'|'processes'|'timeout'|'balance'|memory_limit"
grep -rn -e '->onQueue(' -e 'public \$queue' -e '\$this->queue =' app/
grep -rn -e '->delay(' -e 'dispatch(.*)->delay' app/
grep -rn -e '\$timeout' -e '\$tries' app/Jobs/
```

- **Queue names** — one managed queue per name, if managed queues survive the other checks.
  Plan allowance is per environment.
- **`processes`** — sizes the worker cluster. One process with a 64 MB memory limit needs the
  smallest instance available.
- **`timeout`** — the disqualifier people miss. A job permitted twenty minutes will not
  survive on a Flex managed-queue worker, which gets ~90 seconds to finish when interrupted.
- **`->delay(` with a variable or date** — the other disqualifier. Standard managed queues cap
  per-job delays at 15 minutes; FIFO queues support none.

## Scheduler

```bash
cat routes/console.php 2>/dev/null || cat app/Console/Kernel.php
grep -cE 'everyMinute|everyTwoMinutes|everyFiveMinutes' routes/console.php app/Console/Kernel.php 2>/dev/null
grep -E 'Schedule::call|Schedule::job|Schedule::command' routes/console.php 2>/dev/null
grep -E 'withoutOverlapping|onOneServer' routes/console.php app/Console/Kernel.php 2>/dev/null
```

- **Frequency** — anything more frequent than the sleep timeout means the environment never
  sleeps. Compare against the shortest timeout the dashboard offers.
- **`Schedule::call` versus `Schedule::job`** — `call` runs the callable inside the scheduler
  process; `job` dispatches. A `call` doing network I/O holds the scheduler for its duration,
  and `withoutOverlapping` then suppresses the next tick rather than queueing behind it, so a
  slow run silently skips a sweep. This is true on any host, not just Cloud.
- **`withoutOverlapping` / `onOneServer`** — each acquires a cache lock every run, which keeps
  an attached cache awake and stops it scaling to zero.
- **`onOneServer` absent with multiple replicas planned** — flag it; `withoutOverlapping` is
  not the method intended for that.

## Filesystem — the one that breaks after a successful deploy

```bash
cat config/filesystems.php
grep -rn "Storage::disk(.*)->path(\|storage_path('app" app/
grep -rn "Storage::url(\|asset('storage\|url('storage\|/storage/" app/ resources/views/ routes/
grep -rn 'storage:link' composer.json *.sh .github/ 2>/dev/null
ls -la public/storage 2>/dev/null
du -sh storage/app 2>/dev/null
```

- **`->path()` on a disk that becomes S3** — no local path exists. Every call site needs
  rewriting to a stream, a temporary file, or `temporaryUrl()`.
- **`public/storage` symlink** — cannot exist on Cloud. Trace how each public file reaches a
  browser: an application route in front of the storage path is a one-line repoint and every
  URL already issued survives; a template or email embedding `/storage/...` does not.
- **Both private and public files** — two buckets. Object storage sets visibility per bucket,
  so a bucket cannot hold a mix.
- **Anything written to local disk by a job** — worker ephemeral disk scales with worker
  memory. Exports and PDF generation are the usual cases; size the worker for the largest one.

## Egress and external dependencies

```bash
grep -nE "'host'|'url'|_HOST|_ENDPOINT" config/database.php config/services.php
grep -rn 'SoapClient\|->connection(' app/ --include='*.php' | head -40
grep -rn 'exec(\|proc_open(\|shell_exec(' app/
```

- **Extra database connections** — reporting replicas, legacy systems, warehouses. Each is an
  outbound connection from Cloud, and the far end usually filters by source address. Get the
  environment's egress addresses allowlisted before the first deploy.
- **SOAP, SFTP, payment gateways, monitoring pings** — same question: does the far end
  allowlist?
- **`exec` of a native binary** — Cloud runs ARM64 (Graviton). Transparent for PHP and for
  every extension Cloud provides, but a bundled x86 binary is not.

## State that assumes a machine

```bash
grep -rn 'UsedDiskSpaceCheck\|CpuLoadCheck\|HorizonCheck\|RedisCheck\|DatabaseCheck' app/
grep -rn 'Cache::lock\|->block(' app/
grep -E 'CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION|APP_MAINTENANCE_DRIVER|FILESYSTEM_DISK' .env.example
```

- **Disk-space and CPU-load health checks** measure a server that no longer exists in a
  meaningful sense. Database, cache and Horizon checks stay.
- **`Cache::lock(...)->block(n, ...)`** — the strongest argument for keeping a Redis-compatible
  cache rather than moving cache and sessions to the database. On a database driver `block()`
  becomes a polling loop, and lock contention usually arrives at exactly the busiest moment.
- **`file` drivers for cache, sessions or maintenance mode** — the filesystem is ephemeral and
  unshared. Maintenance mode specifically needs the `cache` driver backed by a shared store,
  or `php artisan down` applies to one replica.

## Sizing inputs from the running system

Ask, or read off the host:

```bash
free -m                              # RAM in use vs installed
nproc                                # vCPU
du -sh /var/lib/mysql                # or the equivalent data directory
du -sh storage/app                   # file volume
```

The number that matters is **in use**, not installed. A stack using 2 GB of 16 sizes nothing
like one using 12.

## What a clean scan looks like

No long delays, no long job timeouts, no Horizon, no local-disk storage, no extra database
connections, no machine-shaped health checks. That application takes managed queues, one App
cluster, a database, a cache, and one bucket — and the assessment is mostly a costing
exercise. Say so plainly rather than manufacturing findings.
