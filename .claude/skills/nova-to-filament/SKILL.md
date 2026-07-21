---
name: nova-to-filament
description: Analyse an existing Laravel Nova application and its customisations against a fixed taxonomy of Nova primitives, then emit a cpm-compliant migration spec that maps every Nova primitive one-to-one onto a specified FilamentPHP version. Use when asked to "migrate Nova to Filament", "convert a Nova admin to Filament", "plan a Nova to Filament migration", "audit a Nova installation for Filament", or "produce a Nova to Filament migration spec" — or when starting the migration-planning phase for a Laravel app moving off Nova. Produces a docs/specifications spec (feeding /cpm:epics) built from a reconciliation ledger that accounts for every Resource, field, action, filter, lens, metric, dashboard, card, tool, policy and relationship. Do NOT use to perform the migration or write Filament code (that is downstream execution via /cpm:do), and not for non-Nova admin panels (Backpack, etc.).
---

# Nova → Filament Migration Spec

Audit an existing Laravel Nova application — including all of its customisation — against a fixed
taxonomy of Nova primitives, reconcile every audited item into a hard-gated ledger, and emit a
**cpm-compliant migration specification** that maps every Nova primitive one-to-one onto a specified
FilamentPHP version. The emitted spec feeds `/cpm:epics`, so the migration plan downstream is only as
complete as this skill's audit.

This skill is structurally a sibling of `solid-spec`: it does an analytical sweep of the codebase
first and emits a spec directly, rather than facilitating requirements conversationally.

## What this skill does — and does not — do

- **Does**: inventory every Nova primitive, read each one's *implementation* (not just its declaration),
  map each to its Filament equivalent for the target version, and write a spec whose Acceptance
  Criteria Coverage table is a complete reconciliation ledger.
- **Does not**: perform the migration, write any Filament code, or run codemods — that is downstream
  execution. It also does not handle non-Nova admin panels.

The completeness guarantee is structural: the audit walks a **fixed taxonomy** (see
`references/nova-taxonomy.md`), every category reports a count even when zero, and every audited item
must resolve to exactly one disposition before the spec can be written. Nothing is dropped silently.

## Process overview

The skill runs as a fixed pipeline. Each phase is documented in its own section below.

1. **Inputs & invocation** — resolve the target Nova app path, the pinned target Filament version,
   and the installed Nova version. Refuse to run if the target is not a Nova app.
2. **Tooling discovery** — find authoritative tooling (Laravel Boost, `composer`, `artisan`) and
   prefer it when present; degrade gracefully to `Glob`/`Grep`/`Read` when absent.
3. **Audit** — walk the fixed Nova taxonomy, count every category, and read each found primitive's
   implementation body and callbacks, capturing behaviour with `file:line (symbol)` citations.
4. **Ground** — start from the bundled mapping tables, then confirm/correct each mapping against the
   pinned Filament version's official docs and the target's installed `vendor/filament` source, which
   wins on conflict. Anything neither source can resolve becomes `needs-human`.
5. **Reconcile** — assign every audited item exactly one disposition (`direct` / `behaviour-change` /
   `[rebuild]` / `needs-human`) in a ledger that reconciles back to the census total, report the
   disposition counts, and run a hard pre-write gate; the spec cannot be finalised while any item is
   unresolved.
6. **Emit** — write the cpm-compliant spec (following `references/spec-template.md` exactly) plus the
   coverage manifest, version-stamped, with the reconciliation ledger doubling as the Acceptance
   Criteria Coverage table and a migration sequencing hint for downstream `/cpm:epics`.

> **Read-only against the target.** Every phase that touches the target Nova codebase is read-only —
> reads, greps, and `composer.lock`/`vendor` inspection only. The skill's only writes are the emitted
> spec and its companion manifest under `docs/`.

## Inputs & invocation

The skill needs two things from the user, and resolves a third from the codebase:

1. **Target Nova app path** — the root of the Laravel application being migrated (the directory
   containing `composer.json` and `artisan`). Defaults to the current working directory when omitted.
2. **Pinned target Filament version** — the Filament version the migration targets, as a concrete
   constraint (e.g. `^4.0`, `4.2.*`, `3.3`). This is **required** — the Nova→Filament mapping differs
   between Filament majors, so a spec written against the wrong version is actively misleading.
3. **Installed Nova version** — resolved from the target, not asked for (see below).

### Argument parsing

Parse the invocation arguments in this order:

1. **A path plus a version** (e.g. `/nova-to-filament ./ ^4.0` or `/nova-to-filament apps/admin 4.2.*`)
   — first token that resolves to a directory is the target path; the token matching a version
   constraint (`^`, `~`, digits with `.` / `*`) is the pinned Filament version.
2. **A version only** — target path defaults to the current working directory.
3. **A path only, or empty** — the pinned Filament version is missing. Do **not** guess it. Ask the
   user with `AskUserQuestion` which Filament major/version to target before proceeding.

Record the two confirmed inputs; they are needed for grounding (mapping against the pinned version)
and for stamping the emitted spec header.

### Nova detection (must run before anything else)

Confirm the target is actually a Nova application before doing any audit work. Check, in order of
authority:

1. **`composer.lock`** — the authoritative record of what is installed. Look for a package entry with
   `"name": "laravel/nova"` and read its `"version"`. This is the **installed Nova version** to stamp.
2. **`composer.json`** — `require` should list `laravel/nova` (often via a private Composer repo, since
   Nova is a paid package). Use this as a fallback for the constraint if `composer.lock` is absent.
3. **`app/Nova/`** — the conventional home of Nova Resources; and a `NovaServiceProvider` under
   `app/Providers/`. Their presence corroborates a Nova install even when the lock file is unavailable.

Also record, for context in the emitted spec header, the Laravel framework version
(`laravel/framework` in `composer.lock`) and the PHP constraint — these bound what Filament version is
even installable.

### must NOT: no Nova, no spec

If **no** Nova installation is detected — no `laravel/nova` in `composer.lock`/`composer.json`, and no
`app/Nova/` resources or `NovaServiceProvider` — the skill **must NOT** emit a spec. There is nothing
to migrate, and a spec built from an empty audit would be a false artifact. Stop and report what was
checked and not found, so the user can correct the target path or confirm the app really is on Nova.

If the installed Nova version cannot be resolved (e.g. `composer.lock` missing and `composer.json`
carries only a loose constraint), do not fail outright: record the version as
`unknown (constraint: {constraint})`, warn the user that mapping precision is reduced, and continue —
the audit itself does not depend on the exact patch version, only the grounding does.

## Tooling discovery

Before auditing, discover what authoritative tooling the target provides and **prefer it when present** —
it makes version resolution and API grounding sharper. Discovery is mandatory *behaviour*; none of the
tools is a mandatory *dependency*. The audit must run to completion whether or not any of them are found.

### Probe order

Probe for, and prefer, the most authoritative source available:

1. **Laravel Boost** (`mcp__laravel-boost__*`), if installed — the sharpest source:
   - `application-info` — confirms Laravel/PHP versions and the installed package list (including the
     exact `laravel/nova` version and any `*/nova-*` packages), so counts and version stamps are precise.
   - `search-docs` — version-pinned Laravel docs, useful when a resource leans on a framework feature.
   - `tinker` — read-only probing of registered state (see the runtime cross-check below).
2. **`composer` / `composer.lock` / `composer.json`** — the authoritative record of installed versions and
   packages when Boost is absent. `composer show -i` (or reading `composer.lock`) resolves the Nova,
   Laravel, and PHP versions and surfaces `nova-*` packages.
3. **`artisan`** — `php artisan about` corroborates the framework/version picture; its presence also
   confirms this is a Laravel app.

### Fallback: always degrade, never gate

When none of the above is available, **fall back to `Glob`/`Grep`/`Read`** over the source tree — the
static audit needs nothing more than file access. Discovery sharpens the audit; it never gates it. Record
which tools were found and used (and which were absent) in the coverage manifest, so a reader knows how
authoritative the version/API grounding for this run was.

### Runtime cross-check & dynamic registration

The audit is **static-first**: the census and the implementation reads come from the source tree, which is
portable, read-only, and reproducible without a bootable app. Runtime reflection is an **optional
cross-check layered on top** — never a requirement, because a cleanly-bootable app is rarely available
mid-migration and booting an unknown target is operationally risky.

**When available, use it to confirm — not to replace — the static census.** If Laravel Boost's `tinker`
(or an equivalent read-only runtime probe) is available, ask the running app what Nova actually registered
— e.g. the resources, tools, dashboards, and cards known to `Nova::` at runtime — and diff that against the
static census. The value of the cross-check is catching what static analysis structurally cannot see:

- resources/tools/cards registered dynamically (a loop, config, or discovery in `NovaServiceProvider`);
- fields added via shared traits or `->merge()` that a per-file grep under-counts;
- package-supplied primitives that register themselves at boot.

**Reconcile the diff, and route the unconfirmed to `needs-human`.** Where the runtime set matches the
static census, the count is confirmed. Where they differ — or where no runtime probe is available and the
*things that hide* check (see the taxonomy walk) could not confirm completeness — the affected items are
recorded as **`needs-human`**, not silently reconciled and not assumed to be zero. A `needs-human` item is
an honest "the audit could not confirm this by itself"; it carries forward into the reconciliation ledger
as an unresolved row that a person must dispose of before the spec can be finalised. Runtime probing
shrinks the `needs-human` set; its absence simply leaves those items flagged rather than hidden.

## Audit: walk the Nova taxonomy

The audit is driven by the **fixed taxonomy** in `references/nova-taxonomy.md`, not by whatever happens to
turn up in a grep. Walk the taxonomy top to bottom and, for **every** category, produce a count — even
when that count is zero. Completeness is achieved by construction: you cannot forget to look for Lenses if
Lenses are a mandatory line in the walk.

### The walk

For each category in `references/nova-taxonomy.md` (Resources; Fields; Actions; Filters; Lenses; the four
Metric sub-types; Dashboards; Cards; Relationships; Policies/Authorization; Custom Tools; and the
cross-cutting registration/chrome group):

1. **Locate** instances using that category's *Detect* signals — its base class, conventional path, and
   call-site grep. Prefer authoritative tooling when available (see *Tooling discovery*); fall back to
   `Glob`/`Grep`/`Read`.
2. **Count** the instances found and record the count against the category — **including zero**. A
   zero-count category is an explicit, recorded result ("Lenses: 0"), never an omission.
3. **List** each instance with its `file:line (symbol)` location, to be read in depth in the next phase.
4. **Note the hiding places.** For each category, actively check the *things that hide* (dynamic
   registration, shared traits/base resources, `->merge()`d field sets, package-supplied primitives,
   macros). When you cannot confirm whether the static count is complete, record the uncertainty as a
   `needs-human` item rather than trusting the grep — an unconfirmed count is not a zero.

### Output of the walk: the category census

The walk produces a **category census** — one row per taxonomy category with its count and instance
locations. This census is the top of the coverage manifest and the seed of the reconciliation ledger:
every instance it lists is an item that must later receive a disposition. The census is what makes the
final "142 direct, 9 behaviour-change, 4 rebuild, 2 needs-human" reconcilable back to a known total.

### must NOT: no silent omissions

The audit **must NOT** silently omit any taxonomy category. Every category from
`references/nova-taxonomy.md` appears in the census with a count, and a category is only ever recorded as
zero when its *Detect* signals genuinely found nothing **and** the hiding-places check came back clean. If
a category's completeness cannot be confirmed, it is reported with a `needs-human` note — never dropped,
and never quietly assumed to be zero. A census missing a category is a defect, not a tidy result.

## Audit: read the implementation

The census (from the taxonomy walk) tells you *what exists*. This phase reads *how each thing behaves* —
because that behaviour, not the declared type, is what has to survive the migration. A `Text` field with a
`resolveUsing` closure that reformats a value is not "a text field"; it is a text field **plus a
transform**, and the transform is the part a naïve mapping drops.

### Read the body, not the signature

For every instance in the census, open its implementation and read the actual code — never classify from
the `::make()` line alone. Use the **Capture** column of each category in `references/nova-taxonomy.md` as
the checklist of what to extract:

- **Fields** — read the field's chained modifiers and closures: `rules`/`creationRules`/`updateRules`,
  `resolveUsing`/`displayUsing`/`fillUsing`, `dependsOn`, visibility callbacks
  (`hideWhen…`/`showWhen…`/`onlyOn…`), `readonly`/`nullable`/`sortable`, `default`, `options`/`enum` maps,
  `help`/`placeholder`, and `canSee`. Note computed fields (built from a closure/attribute, not a column).
- **Actions** — read `handle()` (what it does to the models), `fields()` (the action form), queue
  behaviour (`ShouldQueue`), `$destructive`/`$standalone`, confirmation text, and `canRun`/`canSee`.
- **Filters / Lenses / Metrics** — read the query logic (`apply()`, `query()`, `calculate()`), options,
  ranges, and aggregates. For lenses, the custom query is usually the whole point.
- **Policies / Authorization** — read the *rule*, not just its presence: who may view/create/update/delete,
  and every `canSee`/`authorizedTo…` closure.
- **Relationships** — read pivot fields, `relatableQuery` scoping, `MorphTo` type lists, inline creation.
- **Custom Tools / custom fields / custom cards** — read enough of the Vue + backend to scope a rebuild.

### Cite everything

Every captured behaviour records a `file:line (symbol)` citation, exactly as `solid-spec` does. `(symbol)`
may be a class, a resource method (`fields()`), an action's `handle()`, a metric's `calculate()`, or a
specific field (`Text::make('title')`). The spec points at the code; the code stays the source of truth.
Citations are what let a reviewer confirm the audit read the real thing and what let the migration
implementer find it again.

### must NOT: don't launder customisation into "direct"

The disposition itself is assigned later (in the reconciliation phase), but the audit is where the evidence
for it is gathered — so the boundary is recorded here. An item **must NOT** be allowed to be classified
`direct` when this phase found behaviour that has no direct Filament equivalent. Concretely: when you
capture a callback, computed value, conditional, custom validation rule, or bespoke component, record it as
a **behaviour flag** on the item so reconciliation cannot treat the item as a plain type-to-type mapping.
A field that *looks* like a `direct` `Text → TextInput` but carries a `resolveUsing` transform must reach
reconciliation already flagged as behaviour-changing (or `[rebuild]`), never as a bare `direct` candidate.
Capturing the behaviour here is what makes the downstream gate honest.

### Scale & context safety: fan out the audit

A real Nova app can have hundreds of resources and thousands of fields. Reading all of them into a single
context window will overflow it and degrade the audit. The audit is therefore **fanned out**, not read
linearly:

- **Delegate per unit of work.** After the census identifies the instances, dispatch the
  implementation-depth reads as parallel sub-agents — the natural unit is *one resource* (its fields,
  actions, filters, lenses, relationships, and policy read together), plus one sub-agent per standalone
  primitive group (metrics, dashboards, tools). Each sub-agent reads its slice and returns **structured
  findings** (the captured behaviours + `file:line` citations), not raw file contents — so the main
  context accumulates conclusions, not source.
- **Batch large categories.** When a single category is itself huge (e.g. 300 fields across many
  resources), batch it across several sub-agents by resource group rather than one giant read.
- **Self-contained prompts.** Each sub-agent prompt names the target path, the taxonomy category/capture
  checklist it is responsible for, and the exact instances (from the census) to read — it starts with no
  conversational context, so the instruction must carry everything.
- **Reconcile back to the census.** Every sub-agent's findings are merged back against the census by
  instance, so the total still reconciles: `findings count == census count` for each category, or the
  difference is itself recorded (a `needs-human` gap). Fan-out must never lose an item silently — the
  census is the manifest the merged findings are checked against.

The result is that context stays bounded no matter how large the Nova app is, while the census guarantees
nothing dispatched goes unaccounted for on the way back.

## Ground: confirm each mapping against the real Filament

The audit produced a census of Nova items with captured behaviour. This phase decides, for each item,
*what it maps to in the target Filament version* — the raw material the reconciliation phase turns into
dispositions. Do **not** trust the bundled tables blindly and do **not** invent Filament APIs from
memory: the bundled table is a starting hypothesis, and it is confirmed or corrected against the real
Filament for the pinned version before any mapping is treated as settled.

### Sources of truth, in precedence order

For every mapping, resolve the Filament equivalent by consulting these sources. **A later source
overrides an earlier one on conflict** — installed source is the strongest evidence, the bundled table
the weakest:

1. **Bundled mapping tables** (`references/nova-to-filament-v{major}.md`) — the offline baseline. They
   give the candidate Filament construct and a *default* disposition class for each Nova primitive, so
   the common case is fast and needs no network. Treat every row as a hypothesis to verify, never as
   proof. Pick the table whose `{major}` matches the pinned target version; if no table ships for that
   major, every mapping in scope starts unverified and must be grounded from sources 2–3 (or flagged —
   see the fallback below).
2. **The pinned version's official documentation** — the authority on the *documented* API surface for
   that Filament major. Confirm the bundled candidate exists in the pinned version, still carries the
   behaviour the audit relies on, and has not moved namespace or signature between majors. Prefer
   version-pinned docs tooling when present (Laravel Boost `search-docs` scoped to the installed Filament
   package; otherwise the official docs for the exact pinned version — **not** "latest", which may
   describe a different major). Docs override the bundled table when they disagree.
3. **The target's installed `vendor/filament` source** — the **highest** authority, used whenever
   Filament is installed at the target. The installed code is the ground truth for the version actually
   in play: read the real class, its namespace, its public methods and signatures under
   `vendor/filament/**/src`. When the installed source and the docs disagree (docs lag a patch release,
   a method was deprecated, a signature changed), **the installed source wins** — it is what the
   migration will actually compile against.

The precedence is deliberately *installed source > live docs > bundled table*: each step up is closer to
the code the migration will really run on. Record, per mapping, which source confirmed it, so a reader
knows whether a row rests on read vendor code, read docs, or an unverified bundled default.

### When to read `vendor/filament` source

Reading the docs is usually enough for a first-party construct that maps cleanly. Drop to the installed
source when:

- the docs are **missing, ambiguous, or version-mismatched** for the construct in question;
- the bundled table and the docs **conflict**;
- the Nova item carries **captured behaviour** (a callback, computed value, custom rule) whose Filament
  equivalent depends on a specific method or signature that must be confirmed to exist in the installed
  version;
- the mapping is a **`behaviour-change`** where the exact Filament API shapes the migration story.

The source read is read-only, like every other target-touching phase. Cite what you read
(`vendor/filament/.../src/File.php:line (Symbol::method)`) so the grounding is auditable, exactly as the
audit cites the Nova side.

### must NOT: never guess an ungroundable mapping — record `needs-human`

When **neither** the docs **nor** an installed `vendor/filament` source can resolve a mapping — the docs
are silent or contradictory *and* Filament is not installed at the target (or the installed source does
not contain the construct) — the item **must NOT** be given a guessed Filament equivalent. Record it as
**`needs-human`** instead: an honest "the mapping could not be grounded against any authority."

A `needs-human` grounding result carries forward into the reconciliation ledger as an unresolved row
that a person must dispose of before the spec can be finalised — it is never silently defaulted to
`direct`, never assumed to map cleanly, and never dropped. This is the same `needs-human` disposition the
audit uses for unconfirmable counts (see *Runtime cross-check*); grounding simply adds the other reason
an item can land there. Installing the target's Filament, or better docs, shrinks the `needs-human` set;
their absence leaves the item flagged rather than fabricated.

Guessing a mapping is the one failure this skill exists to prevent: a fabricated Filament equivalent
produces a spec that looks complete and is wrong. An unresolved `needs-human` row is visibly incomplete
and therefore safe — it forces a human decision instead of hiding a bad one.

## Reconcile: one disposition per item, gated

The audit produced a **census** (what exists, with captured behaviour and citations) and grounding
produced a **mapping result** per item (its Filament equivalent, or `needs-human`). Reconciliation turns
those into a single **ledger** in which *every* audited item resolves to *exactly one* disposition. The
ledger is the spine of the emitted spec — it becomes the Acceptance Criteria Coverage table in the Emit
phase — and it is protected by a hard gate that will not let an incomplete migration plan be written.

### The reconciliation ledger

Build one ledger with **one row per audited item** — every instance the census listed, across every
taxonomy category. Each row carries at minimum:

| Column | Content |
|---|---|
| **Nova item** | The primitive and its identity — `file:line (symbol)`, e.g. `app/Nova/User.php:41 (Text::make('name'))`. |
| **Disposition** | Exactly **one** of `direct` / `behaviour-change` / `[rebuild]` / `needs-human`. |

Keep alongside them the supporting columns the Emit phase needs — the Filament target construct, the
captured behaviour flags, and the grounding source that confirmed the mapping — but the **disposition is
the load-bearing field**: it is single-valued and mandatory. No row may carry two dispositions, and no
row may be left blank.

### Assigning the disposition

Derive each row's disposition from the audit's behaviour flags and the grounding result — never from the
declared Nova type alone:

- **`direct`** — grounding found a like-for-like Filament construct **and** the audit recorded *no*
  behaviour flags on the item (no callback, computed value, conditional, custom rule, or bespoke
  component). A plain `Text::make('title')` → `TextInput` with nothing chained.
- **`behaviour-change`** — a Filament equivalent exists, but either the API/shape differs materially
  *or* the audit flagged customisation that must be re-expressed (a `resolveUsing` transform, a
  `dependsOn` conditional, a custom validation rule, a re-written query). The item maps, but not
  type-to-type.
- **`[rebuild]`** — no honest Filament equivalent: custom Vue fields/cards, custom Tools, or a feature
  Filament core lacks. These are the highest-risk rows and each becomes its own rebuild story downstream.
- **`needs-human`** — the audit could not confirm the item (an unconfirmable count from the *things that
  hide* / runtime cross-check) **or** grounding could not resolve its mapping against any authority. This
  is the honest "unresolved" state — it is not a mapping, it is a flag that a person must dispose of.

The audit's rule that customisation must not be laundered into `direct` is enforced here: an item that
reached reconciliation carrying a behaviour flag **cannot** be assigned `direct` — it is
`behaviour-change` or `[rebuild]`. The flag set in the audit is what makes this gate honest.

### Reconcile back to the census

The ledger must **balance against the census**: the number of ledger rows in each taxonomy category
equals that category's census count. If they differ, the difference is itself an unresolved item (a
dropped or duplicated instance) and is recorded as `needs-human`, never silently smoothed over. This is
the check that proves nothing fell out between audit and ledger — the same reconcile-to-census discipline
the fan-out audit uses on the way back.

### Report the disposition counts

Once the ledger is built, report the **disposition counts** as a single tally, e.g.:

> **142 direct, 9 behaviour-change, 4 rebuild, 2 needs-human** (157 items; census total 157 ✓)

The counts must sum to the census total, and that reconciliation is shown. The tally is the at-a-glance
proof of completeness and the headline of the coverage manifest.

### The hard gate + pre-write self-check (must NOT write an unresolved spec)

Before the Emit phase writes anything, run this **pre-write self-check** as a hard gate. It is
procedural — a checklist that must pass in full — and the skill **must NOT** finalise or write the spec
while any check fails:

1. **Every census item has a ledger row** — ledger row count == census total, per category and overall.
2. **Every row has exactly one disposition** — none blank, none double-valued.
3. **No customisation laundered into `direct`** — no row flagged with captured behaviour is `direct`.
4. **No unresolved rows remain.** Any `needs-human` row is an unresolved row. The spec **must NOT** be
   written while any `needs-human` row is outstanding.

**Routing `needs-human` items (AD5).** When the self-check finds `needs-human` rows, do **not** silently
default them and do **not** write the spec. Surface them to the user with `AskUserQuestion` — present
each unresolved item (its identity, why it could not be resolved, and the candidate dispositions) and let
the user choose its disposition, so the human decision is made *before* finalising, not discovered after.
Only once every `needs-human` row has been dispositioned by the user — or the user explicitly accepts
them as documented open items — does the gate pass and the Emit phase proceed. An unresolved ledger is a
blocked spec, by design: the gate would rather stop and ask than emit a plan with a hidden hole.

## Emit: write the cpm-compliant migration spec

Only after the reconciliation gate passes, write the output. The emitted spec is the skill's product; it
must slot into the cpm pipeline so `/cpm:epics` can break it down without special-casing.

### Follow the bundled template — do not fork the contract

Build the spec to `references/spec-template.md` **exactly**: the section headings, MoSCoW labels, `R{N}`
requirement labelling, tag vocabulary, and Acceptance Criteria Coverage columns are the cpm contract
(the same one `cpm:spec` and `solid-spec` emit). This skill adds only three things, all already in the
template: the version-stamped header lines, the `[rebuild]` tag in the tag vocabulary, and the trailing
**Disposition** column that makes the coverage table *be* the reconciliation ledger. Do not paraphrase
the contract prose and do not invent a bespoke migration-report format — a fork breaks the downstream
handoff. If in doubt about the current canonical structure, cross-check against `/cpm:templates preview
spec`; the bundled template mirrors it.

### One functional requirement per Nova primitive

Every audited Nova primitive becomes **one Must-Have requirement** with a unique `R{N}` label — this is
what makes the migration *one-to-one and traceable*. The requirement states, in MUST language, that the
Nova primitive is reproduced as its Filament target (from the grounded mapping), preserving the captured
behaviour, with the Nova source cited inline as `file:line (symbol)`. Should/Could/Won't tiers hold the
non-blocking and deferred items. A primitive dispositioned `[rebuild]` still gets its requirement — its
acceptance criterion scopes the rebuild rather than a mapping.

### The ledger *is* the Acceptance Criteria Coverage table

Render the reconciliation ledger as the spec's Acceptance Criteria Coverage table: one row per audited
item, the first three columns the canonical contract (Requirement, Acceptance Criterion, Test Approach)
and the trailing **Disposition** column carrying the item's single disposition. The row count reconciles
to the census total, and the Problem Summary's disposition tally is the sum of the Disposition column —
the same numbers the reconcile phase reported. This is the structural guarantee that the spec accounts
for every audited primitive: the coverage table cannot be complete unless the ledger was.

**Parity criteria are behavioural, not pixel-level.** Each acceptance criterion asserts *preserved
behaviour* — the same validation rules, the same authorization gates, the same data transforms, the same
query results — never a visual/pixel match to the Nova UI. Filament renders differently by design; the
migration is correct when behaviour is preserved, not when screens look identical. Tag such criteria
`[feature]`/`[integration]` where a workflow or boundary can exercise them, `[manual]` only where the
oracle is genuinely human judgement.

### Version-stamp the header

Stamp the header from the values resolved in the *Inputs & invocation* phase, using the template's
header lines:

- **Nova version** — the installed version resolved from `composer.lock` (or `unknown (constraint: …)`
  when it could not be resolved).
- **Target Filament version** — the pinned constraint the user supplied (e.g. `^5.0`).
- **Laravel / PHP** — for install-feasibility context.
- **Audit tooling** — which authoritative tooling was used vs. static fallback, so a reader knows how
  authoritative this run's grounding was.

The version stamp is not cosmetic: the Nova→Filament mapping is version-specific, so a spec that does not
record which versions it was written against cannot be trusted or reproduced. The stamp ties the emitted
plan to the exact `{Nova version} → {Filament version}` pairing the audit and grounding assumed.

### Output location

Write the spec to `docs/specifications/{nn}-spec-{slug}.md` in the target project, with `{nn}` assigned
by the standard numbering rule and `{slug}` derived from the panel/app name. This is the same location
and naming `cpm:spec` uses, so the pipeline picks it up transparently.

### The coverage manifest (proves the audit missed nothing)

Alongside the spec, write a **coverage manifest** — a companion artifact that makes the audit itself
auditable. The spec says *what will be migrated*; the manifest proves *the audit looked everywhere and
dropped nothing*. Write it to `docs/specifications/{nn}-spec-{slug}-coverage.md` (same `{nn}`/`{slug}` as
the spec, so the pair travels together). The manifest records:

- **Every file scanned during the audit.** The full list of source files the walk and the fan-out reads
  touched — resources, actions, filters, lenses, metrics, dashboards, cards, tools, policies, the
  `NovaServiceProvider`, `config/nova.php`, and any `*/nova-*` package sources inspected. A reader can
  confirm coverage by checking a file they care about is on the list.
- **The category census.** One row per taxonomy category with its count (including the zero-count
  categories), exactly as the audit produced it — the manifest is where the "count every category, even
  zero" guarantee is visible.
- **The disposition tally.** The same `direct / behaviour-change / [rebuild] / needs-human` counts the
  reconcile phase reported, reconciled to the census total.
- **Every item that could not be classified.** A dedicated section listing each `needs-human` /
  unclassified item with its identity (`file:line (symbol)`) and *why* it could not be resolved
  (unconfirmable count from the *things that hide* check, or an ungroundable mapping). If the user
  accepted any of these as documented open items at the reconciliation gate, that acceptance is recorded
  here too.

### must NOT: no silent omissions

The manifest exists so that **gaps are visible rather than dropped**. The skill **must NOT** omit a
scanned file from the file list, a category from the census, or an unclassified item from the
could-not-classify section. An audit that quietly leaves something off the manifest reintroduces exactly
the false-completeness this skill is built to prevent: a spec that *looks* total because the holes were
never written down. If the audit could not reach a file or confirm a category, that fact is itself a
manifest entry — recorded, not hidden.

### Migration sequencing hint

The emitted spec is a set of one-to-one requirements, but the order they are *executed* matters: a
Filament resource that a relationship points at should exist before the resource that references it, or
the downstream build has nothing to wire to. So the Emit phase includes a **migration sequencing hint** —
a proposed order — in the spec's **Migration Sequencing** subsection (see `references/spec-template.md`),
so downstream `/cpm:epics` inherits a sensible sequence instead of guessing one.

**Derive the order from the relationship graph captured in the audit.** The Relationships category
(taxonomy §9) already recorded, per resource, its `BelongsTo`/`HasMany`/`BelongsToMany`/`MorphTo` edges.
Build a dependency graph from those edges and order the resources so that **dependencies come first**:

1. **Standalone resources first** — those with no outbound relationship dependencies (nothing they
   *belong to*). They are safe to build with nothing else in place.
2. **Then dependents in topological order** — a resource is sequenced after the resources it points at
   (its `BelongsTo`/`MorphTo` targets), so its relationship fields have something to resolve against.
3. **Break cycles explicitly.** Nova apps can have circular references (A belongs to B, B belongs to A).
   When the graph has a cycle, do not loop — record the cycle in the sequencing note and pick a
   pragmatic entry point (usually the resource with fewer inbound edges), flagging that the pair needs
   its relationship fields wired in a second pass.

State the **ordering rule used** alongside the order itself (e.g. "topological by `BelongsTo`/`MorphTo`
edges; standalone resources first; cycle A↔B broken at A"), so the sequence is explainable rather than
arbitrary. The hint is advisory — downstream epics may regroup — but it means the plan ships with a
buildable order baked in rather than leaving sequencing to be rediscovered.
