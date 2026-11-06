---
name: assess-for-laravel-cloud
description: "Assesses an existing Laravel application for migration to Laravel Cloud and produces a sized, costed proposal — which resources it needs, what size each should be, what it will cost per month as a range, and what has to change in the code before the first deploy. Use when asked to cost a move to Laravel Cloud, size an application for Cloud, work out whether an app will run on Cloud, compare Cloud against a current VPS or Forge server, decide between managed queues and a worker cluster, or produce a migration plan or proposal for Cloud. Triggers on 'what would it cost on Laravel Cloud', 'can we move this to Cloud', 'size this for Cloud', 'Cloud migration plan', and 'Forge to Cloud'. For deploying an application already on Cloud, use deploying-to-cloud; for changing one already running there, use evolving-a-cloud-app."
license: MIT
metadata:
  status: sketch
  written: 2026-09-11
  companion-to: deploying-to-cloud
---

# Assessing a Laravel application for Laravel Cloud

**Requires the Laravel Cloud CLI and its `deploying-to-cloud` skill.** Neither ships in this repository. Install the CLI with `composer require --dev laravel/cloud-cli` (or `composer global require laravel/cloud-cli`), then install its skills with `cloud skills:install --global` (or `--project`).

This skill produces one thing: a proposal that says which Cloud resources the application
needs, how big each should be, what it costs per month as a range, and what has to change
in the code first. It does not deploy anything — `deploying-to-cloud` owns the CLI and the
deployment mechanics.

The work is in this order, and the order matters. Sizing before measuring produces a
number nobody can defend; costing before reading the repository misses the thing that
disqualifies half the architecture.

## Step 1 — Measure the current host before reading anything

Ask for these, or read them off the server. Do not proceed on estimates; every one of them
changes the answer.

| Ask | Why it decides something |
|---|---|
| RAM **in use**, not installed | The installed figure is almost always the whole stack's ceiling, not the application's need. A 16 GB box using 2 GB sizes very differently from one using 12 |
| vCPU count | Flex carries one vCPU per replica. This decides whether the answer is more replicas or a Pro size |
| Database size on disk | Sets the storage line, and whether the dataset fits a Flex instance's buffer pool |
| File storage volume | Sets the object storage line, usually to pennies |
| Traffic shape — peaks, quiet hours | Decides whether scale-to-zero is worth anything and whether autoscaling is needed |

State clearly that the installed RAM is not the requirement. It is the most common source
of a wildly oversized — and therefore rejected — proposal.

## Step 2 — Read the repository for the decisions it has already made

`reference/repo-scan.md` holds the exact commands and what each answer means. Run the whole
scan before forming a view; the disqualifiers hide in single lines.

The questions the scan answers:

- **What queue arrangement does this application actually need?** Queue names, worker
  count, job timeout, Horizon, and whether any dispatch uses a long delay.
- **How often does the scheduler run, and does it do work inline?** This decides whether
  scale-to-zero is reachable at all.
- **Where do files live, and how are they served?** The ephemeral filesystem is the most
  common thing to break silently after a successful deploy.
- **What does the application dial out to?** Every external host that filters by source
  address needs the environment's egress addresses before the first deploy.
- **Does anything depend on a machine?** Disk-space checks, CPU checks, local locks,
  `exec` of a native binary, a symlink.

## Step 3 — Choose the queue arrangement, by disqualification

Managed queues are the right default. Work down this list, and the first hit decides it:

| If the application… | Then | Evidence to grep for |
|---|---|---|
| dispatches with a delay longer than 15 minutes | Managed queues are out | `->delay(` with a variable or a date |
| needs jobs to run longer than ~90 seconds reliably | Flex managed queues are out; Pro or a worker cluster | `'timeout' =>` in `horizon.php`, `$timeout` on jobs |
| uses Horizon, or `queue:failed` / `queue:retry` / `queue:clear` | Managed queues are out | `laravel/horizon` in `composer.json` |
| relies on Redis-specific queue behaviour or strict ordering | Worker cluster, keeping the driver | Redis queue features, `onOneServer` |
| has none of the above | **Managed queues** | — |

A worker cluster needs the Growth plan. An App-cluster background process is the Starter-plan
fallback, and is only appropriate for low job volume — it competes with web traffic for CPU.

State the reason the default was rejected, not just the conclusion. "Managed queues are out
because standby reminders are delayed to an arbitrary future date" is a sentence someone can
check; "we recommend a worker cluster" is not.

## Step 4 — Size from the measured footprint

Split the measured working set across the resources it becomes. One VM running web, database,
cache and workers becomes four line items, and each is sized for its own job.

- **App cluster.** What the web server and its PHP workers use, plus headroom. Flex tops out
  at 2 GiB; above that the answer is Pro, or more replicas. Cloud sets PHP's `memory_limit`
  as a proportion of instance memory, so a small instance gives a small per-request ceiling —
  check the heaviest request path, usually an export or a report.
- **Worker cluster.** Process count × per-job peak. A single `queue:work` process with a
  64 MB Horizon memory limit does not need a gigabyte. Check the largest job, not the average.
- **Database.** The hot set, not the dataset. A 3 GB database whose whole host uses 2 GB has
  a working set well under 2 GB. Size to the hot set, name the dataset, and say which
  instance size is the upgrade if query latency disagrees.
- **Cache.** Usually the smallest size offered. Say what it serves — cache, sessions, queue,
  locks — because that list is what stops someone "saving" it later.
- **Object storage.** Volume × the per-GB rate. Almost always pennies. Two buckets if the
  application serves both private and public files: visibility is per bucket.

## Step 5 — Cost it as a range, never a single number

`reference/rates.md` holds the published rates and how to refresh them, including the fact
that the pricing page renders its table from JSON and exposes other regions only through a
selector.

Three figures, on one scale:

- **Floor.** Everything that can scale to zero doing so for a realistic share of the month,
  after any re-architecture the assessment recommends. Only Flex sizes scale to zero; Pro
  sizes never do.
- **Expected.** Everything always on at the chosen sizes, one replica. This is the number to
  plan against, because it is what happens if nothing changes.
- **Ceiling.** The upgrades the assessment named as possible — a Pro database, an extra
  replica — all taken.

Put the plan fee in all three and say so, so the usage figure is separable.

Two items always sit outside the range, and both should be stated rather than modelled:
**preview environments bill exactly like production**, one per open pull request; and
**bandwidth overage** beyond the pooled per-instance allowances.

## Step 6 — Work out whether scale-to-zero is actually reachable

This is where most assessments are quietly wrong, and it is worth a section of its own in
the proposal.

An environment wakes to run scheduled tasks, and **a wake keeps it awake for one whole sleep
timeout**, not for the duration of the task. So the schedule interval must exceed the sleep
timeout, or the environment never sleeps. A task every five minutes against a five-minute
timeout sleeps never.

Two ways out, and they are not equivalent:

1. **Shorten the sleep timeout below the interval.** Keeps every task at its current
   frequency. Requires that each task completes inside the timeout — so a scheduled closure
   doing network I/O inline must move to the queue first.
2. **Lengthen the interval, or bound it to a window with `->between()`.** No code change, but
   it delays whatever the sweep does, which is a product decision when the sweep controls
   something a customer sees.

Also check the cache. The scheduler queries the cache store for every task using
`withoutOverlapping` or `onOneServer`, so frequent tasks using those keep an attached cache
awake and stop it scaling to zero too. A per-minute schedule costs both lines, not one.

## Step 7 — List the porting work, ordered by what stops a deploy

Every assessment produces some of these. Order them by likelihood of breaking a deployment,
not by effort.

1. **The filesystem.** Ephemeral, not shared between replicas, and `storage:link` cannot be a
   deploy command. Trace how public files reach a browser today and say exactly what breaks.
   Where an application route already sits in front of the storage path, repointing it is a
   one-line change and every URL already issued keeps working; where templates or emails
   embed `/storage/...` directly, they do not.
2. **`Storage::disk(...)->path()`.** No meaning on an S3 driver. List every call site.
3. **Egress.** Name every external host that filters by source address, and get the
   environment's addresses allowlisted before the first deploy rather than after the first
   failed nightly job.
4. **Health checks and monitoring** that measure a machine — disk space, CPU load, load
   average — describe a server that no longer exists.
5. **PHP version.** Pin it. New environments default to the newest supported version, which
   may not be the one the application is tested on.
6. **Environment variables** the configuration reads but `.env.example` does not carry.
7. **Build and deploy commands.** Optimisation in the build, migrations in the deploy.
   Never `queue:restart`, `horizon:terminate`, `optimize:clear` or `storage:link`.

## Step 8 — Write the proposal

Structure that works, in this order: the cost range up front as the thesis; the architecture
and a per-resource ledger; what it replaces; the cost profile with its three scenarios; the
porting work; and what was decided against, with the reason for each.

Two rules for the document:

- **Mark every estimated figure as estimated**, and name what would confirm it. A proposal
  that presents an inferred number with the same confidence as a measured one is the kind
  that gets quoted back after the first invoice.
- **Write down the rejected options and why.** The reader's first question is always "why not
  managed queues" or "why not Postgres", and answering it in the document saves the meeting.

## Rules

- Measure before sizing. Installed RAM is not a requirement.
- Run the whole repository scan before forming a view. The disqualifiers are single lines.
- One disqualifying fact beats a general preference. Managed queues are the default until a
  grep says otherwise.
- Give a range, not a number, and say what moves it.
- Name the region and say plainly when a rate came from a different one.
- Never present an inference as a measurement.

## Reference

- `reference/repo-scan.md` — the commands to run against the repository, and what each result
  means for the architecture.
- `reference/rates.md` — published rates, the always-on cap, plan tiers, and how to refresh
  all of it.
