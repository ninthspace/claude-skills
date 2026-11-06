# Rates, and how to refresh them

**Every figure here was read on 2026-09-11 and will drift.** Refresh before quoting, and say
in the proposal when a rate was read and for which region.

## Where the numbers actually live

The pricing page at `https://laravel.com/cloud/docs/pricing` renders its size tables from an
embedded JSON blob through a React component, so **the per-size prices are not in the page's
markdown or its `.md` variant**. Two consequences:

- Fetching `pricing.md` gets you the prose, the plan tiers and the worked examples, but not
  the tables.
- The region selector is the only route to non-default regions. Ask the user for a screenshot
  of each tab — Compute, MySQL, Serverless Postgres, Valkey, Reverb, Object Storage,
  Bandwidth, Managed Queues — for the region they will actually use.

The *Example scenarios* section of the pricing page is the one place rates appear as text,
which is where the US East figures below come from.

## Structural rules that outlast any price

- **Always-on resources are capped at 672 hours (28 days) per billing cycle.** A continuously
  running replica costs 672 × its hourly rate, not 720 ×. The published "monthly cap" column
  is this figure, and it reconciles exactly with the per-second rate.
- **Only Flex sizes scale to zero.** App compute, Laravel MySQL, Laravel Valkey and worker
  clusters all can; every Pro size is always-on. This is why a Pro upgrade costs more than the
  difference in hourly rate suggests.
- **A worker cluster either stays awake or follows the App cluster** and sleeps with it.
- **Bandwidth allowance accrues per running instance-second** and pools across the whole
  organisation; overage is $0.10/GB.
- **Preview environments bill like production.**

## EU West (London) — compute, read 2026-09-11

| Size | vCPU | RAM | Monthly cap | Per second |
|---|---|---|---|---|
| Flex 512 MiB | 1 | 512 MiB | $7.00 | $0.00000289352 |
| Flex 1 GiB | 1 | 1 GiB | $14.00 | $0.00000578704 |
| Flex 2 GiB | 1 | 2 GiB | $28.00 | $0.00001157407 |
| Pro 4 GiB | 1 | 4 GiB | $34.00 | $0.00001405423 |
| Pro 8 GiB | 2 | 8 GiB | $68.00 | $0.00002810847 |
| Pro 16 GiB | 4 | 16 GiB | $136.00 | $0.00005621693 |
| Pro 32 GiB | 8 | 32 GiB | $272.00 | $0.00011243386 |

London compute sits **16.7% above US East (Ohio)**. Applying that uplift to other resource
classes is a working assumption, not a published relationship — mark any line derived that
way as an estimate.

## US East (Ohio) — from the pricing page's worked examples

| Resource | Rate |
|---|---|
| Flex compute 512 MB / 1 GB / 2 GB | $0.00893 / $0.01786 / $0.03571 per hour |
| Pro compute 4 GB | $0.04762 per hour |
| Laravel MySQL Flex 2 GB / Pro 4 GB | $0.03274 / $0.07143 per hour |
| MySQL storage | $0.10 per GB-month |
| Laravel Valkey 250 MB / 1 GB / 2.5 GB | $0.00893 / $0.03571 / $0.07143 per hour |
| Serverless Postgres | $0.106 per vCPU-hour; storage $0.50 per GB-month |
| Managed queue workers, Flex 256 MiB / 512 MiB | $0.00548 / $0.01096 per hour |
| Managed queue operations | $1 per million standard, $1.10 FIFO |
| Object storage | $0.02 per GB-month; Class A $0.005/1,000, Class B $0.0005/1,000; transfer free |
| Bandwidth over allowance | $0.10 per GB |

Postgres storage is five times MySQL storage per GB — worth noting whenever an engine change
is on the table.

## Plans

| | Starter | Growth | Business |
|---|---|---|---|
| Fee | $5/mo | $20/mo | $200/mo |
| Usage credit | $5 | $5 | $5 |
| Worker clusters | no | yes | yes |
| Autoscaling | no | up to 10× | unlimited |
| Compute / database / cache sizes | Flex only | Flex & Pro | Flex & Pro |
| Managed queues per environment | 1 | 10 | unlimited |
| Log & metrics retention | 1 day | 7 days | 30 days |

The plan fee belongs inside every figure in the range, stated separately so the usage
component is visible.

## Managed queue operations, when they apply

Three operations per successful job — dispatch, start, delete — plus extra for each retry, a
periodic visibility extension on long jobs, and one per 64 KB of payload beyond the first.
Maximum payload 1 MiB. Cloud also long-polls each queue, which comes to roughly **$0.13 per
queue per month** whether or not any job runs. That figure is the floor cost of an idle
managed queue, and it is worth quoting because it is the line that makes managed queues look
free at small volume.

## Worked shape of a range

For a small application, on Growth, in London:

```
floor     plan $20 + usage ~$35    everything Flex and asleep ~half the month
expected  plan $20 + usage ~$68    everything always on, one replica
ceiling   plan $20 + usage ~$125   Pro database, two replicas averaged
```

Quote all three. The middle one is what happens if nothing changes, so it is the number to
plan against; the floor is what the recommended re-architecture buys; the ceiling is what
measured load might force.
