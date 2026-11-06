---
name: evolving-a-cloud-app
description: "Plans and executes architectural changes to an application on Laravel Cloud — choosing between current and deprecated resource types at provisioning time, resizing compute, changing database engine or version, moving region, replacing a cache, and migrating queues or object storage. Use when the user wants to scale up or down, upgrade MySQL, move to Postgres, change region, swap Upstash for Valkey, get off a queue cluster, migrate files into Cloud Object Storage, pick between a managed queue and a worker cluster, or asks whether a Cloud change is reversible or what it will cost. Triggers on resize, scale, upgrade, migrate, move, replace, retire, deprecate, and 'can we undo this' for Laravel Cloud resources. For first deployments and routine deploys, use deploying-to-cloud instead."
license: MIT
metadata:
  status: sketch
  written: 2026-09-11
  companion-to: deploying-to-cloud
---

# Evolving an application on Laravel Cloud

**Requires the Laravel Cloud CLI and its `deploying-to-cloud` skill.** Neither ships in this repository. Install the CLI with `composer require --dev laravel/cloud-cli` (or `composer global require laravel/cloud-cli`), then install its skills with `cloud skills:install --global` (or `--project`).

`deploying-to-cloud` owns deploys, provisioning, and the `cloud` CLI mechanics. This
skill owns what happens when the shape of a running application has to change. Use both:
the CLI flags, auth, and config precedence are documented there and are not repeated
here.

Every change in this skill touches something a user depends on. The order is always:
**read the current state, classify the change, fetch the authoritative procedure, present
a plan, gate on it, then execute one step at a time.** Never start a migration because it
was implied.

## Step 1 — Read the current state before proposing anything

```shell
./vendor/bin/cloud application:get --json -n
./vendor/bin/cloud environment:get {environment} --json -n
./vendor/bin/cloud instance:list --json -n
./vendor/bin/cloud database-cluster:list --json -n
./vendor/bin/cloud cache:list --json -n
./vendor/bin/cloud usage --detailed --json -n
```

Delegate these to a subagent — the combined JSON is large and only the summary matters.

Read the repository alongside it. `config/queue.php`, `config/database.php`,
`config/filesystems.php`, and the environment's `QUEUE_CONNECTION`, `CACHE_STORE`,
`SESSION_DRIVER` and `DB_CONNECTION` decide which path a migration takes more often than
anything in the Cloud dashboard does.

## Step 2 — Classify the change

| Class | Examples | Path |
|---|---|---|
| **Additive** | Add a cache, bucket, domain, background process, instance | `deploying-to-cloud` checklists. Redeploy afterwards |
| **Resize** | Instance size, queue memory, autoscaling range | `instance:update` / `instance:sizes`. Reversible |
| **Replace** | Upstash → Valkey, queue cluster → managed queue, another provider's S3 → Cloud Object Storage | Knowledge-base procedure below. Run both in parallel, cut over, then delete |
| **Convert** | MySQL → Postgres, MySQL 8.0 → 8.4 | Knowledge-base procedure. Irreversible or near enough. Back up first |
| **Relocate** | Different region | Build parallel infrastructure, cut over manually. No in-place path exists |
| **Retire** | Deleting a queue cluster, an old database, an old application | Last step of a Replace or Relocate, never a first step |

Announce the class you picked and why. If the user's request spans two classes — "move
us to Postgres in the new region" — do them as separate, sequenced changes, and say so.

## Step 3 — Fetch the authoritative procedure

Do not work from memory, and do not rely on the model knowing these. Fetch the article
before planning:

| Change | Fetch |
|---|---|
| Region move | `https://laravel.com/cloud/docs/knowledge-base/migrate-to-different-region.md` |
| MySQL 8.0 → 8.4 | `https://laravel.com/cloud/docs/knowledge-base/upgrade-mysql-to-84.md` |
| MySQL → Postgres | `https://laravel.com/cloud/docs/knowledge-base/migrating-mysql-to-postgresql.md` |
| Upstash → Valkey | `https://laravel.com/cloud/docs/knowledge-base/migrate-upstash-to-valkey.md` |
| Queue cluster → managed queue or worker cluster | `https://laravel.com/cloud/docs/knowledge-base/migrate-from-queue-clusters.md` |
| Object storage into Cloud | `https://laravel.com/cloud/docs/knowledge-base/migrating-object-storage.md` |
| Sizing managed queue memory | `https://laravel.com/cloud/docs/knowledge-base/sizing-queue-worker-memory.md` |
| Anything else | `https://cloud.laravel.com/docs/llms.txt`, then the matching `.md` |

Every docs page is available as `.md`, so fetch that rather than the HTML.

Three of these articles carry a prompt written for a coding agent. Where one exists,
follow it — it is the platform team's own statement of the gates for that migration.

## Step 4 — Know what you cannot do from the CLI

Stop and hand these to the user with exact click-through instructions. Attempting them
through the CLI wastes a turn and teaches the user the wrong thing.

- **Upgrading MySQL 8.0 to 8.4.** An "Upgrade" button on the banner under
  *Org > Resources > Databases*. Irreversible. Take a manual backup first (*Backups >
  New backup*) — it is the only recovery path. The database is briefly unavailable, so
  pick a low-traffic window.
- **Moving a domain between environments.** The *Move* item in the domain's `...`
  dropdown under the environment's Network settings. A primary domain must be unset as
  primary first, then set as primary again on the target.
- **Copying a database across regions.** There is no platform feature. Export
  (`mysqldump`, `pg_dump`, or a Neon export), create the target database, import.

## Step 5 — Present the plan, then gate

The plan states, in this order: what changes, what it costs, what breaks during cutover,
whether it can be undone, and what proves it worked. Then stop and wait.

Gate individually — not once for the whole migration — before:

- creating any billable resource;
- any deployment that changes a connection variable (`QUEUE_CONNECTION`, `CACHE_STORE`,
  `SESSION_DRIVER`, `DB_CONNECTION`);
- enabling maintenance mode;
- **anything irreversible**, listed below.

## Step 6 — Reversibility, which decides how hard to gate

| Change | Reversible? | The precaution |
|---|---|---|
| Add or resize a resource | Yes | None beyond a redeploy |
| Object storage migration | Yes, incrementally | A read-through fallback disk serves misses from the old provider while files copy |
| Upstash → Valkey | Yes until teardown | Verify key counts match, or the old queue is empty |
| Queue cluster → replacement | Yes until deletion | Copy the cluster's settings out first — they are discarded with it |
| MySQL → Postgres | Only by keeping MySQL | Keep the source database until the application is verified |
| Region move | Only by not deleting | Keep the old application, database, cache and cluster until cutover is verified |
| **MySQL 8.0 → 8.4** | **No** | A manual backup taken immediately before |

## Step 7 — Verify before retiring anything

Retirement is a separate, later decision. Before deleting the thing being replaced:

- the replacement has handled real traffic or a real job on every name the old one served;
- logs show no errors attributable to the change;
- for queues, `queue:monitor` reports zero jobs on the old connection, delayed jobs
  included, and failed jobs have been retried or deleted — a retried failed job
  re-dispatches onto the connection recorded on it, which can be the connection you just
  emptied;
- the user has explicitly confirmed. Say what will be deleted, by name and ID.

## Provisioning: pick the current option, never a deprecated one

Two resource types are deprecated, and the platform refuses to create either, so this is
a naming and expectation problem rather than a risk:

| Need | Provision | Deprecated — cannot be created |
|---|---|---|
| Queue | Managed queue (latest generation) | Queue cluster. Existing ones stop processing jobs on 2026-09-30 |
| Cache / KV | Laravel Valkey | Redis by Upstash. Existing ones are supported until 2027-08-31, then deleted |

If a user asks for a queue cluster or an Upstash cache by name, say it cannot be created
and name the replacement rather than provisioning something adjacent and calling it done.

Choose between the two live queue options on driver dependence:

| Choose | When |
|---|---|
| **Managed queue** (recommended) | Jobs do not depend on driver-specific behaviour. Laravel Cloud injects the `cloud` connection and scales workers on work waiting, down to zero |
| **Worker cluster** | The application needs its own driver — Redis-specific queue features, per-job `delay()` or `release()` longer than 15 minutes, strict ordering, or Horizon. Nothing in the application changes |

### Managed queue constraints, decided before the first deployment

These bind the application's design, so raise them while the queue is being chosen, not
after it exists:

- `aws/aws-sdk-php` must be in `composer.json`. A deployment that creates or updates a
  managed queue without it fails at deploy time.
- Framework minimum: Laravel 11.55.0, 12.63.0 or 13.19.0, read from `composer.lock`.
- One managed queue handles exactly one Laravel queue name. Several names means several
  queues.
- Per-job delays cap at 15 minutes on standard queues; FIFO queues support none at all.
- There is no fixed job runtime limit, but Flex workers run on interruptible capacity and
  get only a 90-second grace period to finish the job in hand when one stops; Pro gets an
  hour. Work that routinely runs longer than 90 seconds belongs on Pro, which costs a 20%
  premium and keeps one worker billed continuously.
- Standard queues are at-least-once with best-effort ordering. Strict ordering needs FIFO,
  and the type is fixed at creation because it is part of the queue name (`orders.fifo`).
- Queue operations are billed separately at \$1 per million (\$1.10 FIFO), roughly three
  per successful job. Worth modelling at high volume; negligible at low.
- Plan allowance is per environment: Starter 1 managed queue, Growth 10, Business and
  Enterprise unlimited.
- **Horizon does not work with managed queues** — it requires a Redis-backed queue — and
  `queue:failed`, `queue:retry` and `queue:clear` are unsupported. Failed jobs go through
  the Queues dashboard or the API. An application that wants Horizon needs a
  Valkey-backed queue on a worker cluster, and that is an architecture decision, not a
  setting.

### Inheriting a queue cluster

Only relevant for infrastructure created before the deprecation. Before migrating one,
record its connection, every queue name it serves (one cluster can serve
`emails,notifications`), and its worker options — backoff, sleep, rest, timeout, tries,
max-jobs, max-time, force. They are discarded with the cluster. Then follow
`migrate-from-queue-clusters`.

## Sizing, when the user asks how big

Managed queue sizes are 256 MiB, 512 MiB, 1 GiB and 2 GiB on Flex, plus 4 GiB and 8 GiB
on Pro. Size for the largest job on the queue with at least 30% headroom above its
measured peak, and split a single outsized job class onto its own queue. Measure rather
than guess — the sizing article carries a full measurement procedure, including the
safety checks to run before dispatching jobs locally.

For compute, only Flex sizes scale to zero. Workers are billed per second while running,
so starting larger in production and reducing after a full business cycle costs less than
diagnosing out-of-memory failures.

## Rules

- Read state first. A plan written without `*:list --json -n` output is a guess.
- One change at a time, verified, before the next.
- Never delete as part of a migration step. Deletion is its own gated decision, later.
- Never run an irreversible change without a fresh backup and explicit approval in the
  same turn.
- When the procedure is dashboard-only, say so and stop. Do not improvise a CLI
  equivalent.
- If the user asks for something the articles do not cover, say what is unknown rather
  than extrapolating from a similar migration.
