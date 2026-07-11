---
name: laravel-dtos
description: Review a Laravel/PHP codebase (usually a scoped subset — a directory, service, feature, or set of changed files) and replace long, repeated argument lists with Data Transfer Objects that carry semantic meaning. Use this whenever the user wants to refactor "data clumps", "long parameter lists", "primitive obsession", or repeated argument groups into DTOs / value objects / spatie/laravel-data objects, or says things like "these methods all take the same five arguments", "extract a DTO from this", "clean up these signatures", or "introduce value objects here". Trigger even when the user names a scope rather than the word "DTO" (e.g. "tidy up the argument lists in app/Services/Booking").
---

# laravel-dtos

Replace long, repeated argument lists in a Laravel/PHP codebase with DTOs that name a
real domain concept. The goal is not "fewer arguments" — it's making an implicit
concept explicit. `(DateRange $dates)` beats `($start, $end)` because the pair *was
always* a date range; the code just never said so.

A good outcome: signatures get shorter, the same cluster stops being re-typed in five
places, wrong-order argument bugs become impossible, and a reader sees domain
vocabulary (`BookingRequest`, `ReconciliationTotals`, `Address`) instead of loose
primitives. A bad outcome: anonymous `DataBag` classes that just shuffle the same
primitives behind a meaningless name. Avoid the latter at all costs.

## Workflow

Work through these phases in order. Don't skip the safety net, and refactor one
concept at a time — never a big bang.

For a concrete, start-to-finish walkthrough of these phases — one clump correctly
extracted, plus two candidates correctly *left alone* — read
**`references/worked-example.md`**. It's the fastest way to see the judgement in action.

### 0. Resolve the scope

The user almost always scopes the review ("the EPOS services", "app/Actions", "the
coupon flow", "what changed in this branch"). Turn that into a concrete file set:

- A path/dir → use it directly.
- A feature/flow → find the classes involved (controllers, actions, services, jobs).
- "Changed files" → `git diff --name-only main...HEAD -- '*.php'`.

If no scope is given, ask for one rather than scanning the whole app — DTO refactors
are judgement-heavy and a focused diff is reviewable; an app-wide sweep is not.

### 1. Establish a safety net BEFORE changing anything

Refactoring without a green test suite is how you ship silent behaviour changes.

- Run the relevant tests first and confirm green: `php artisan test --filter=...` or
  `./vendor/bin/pest path/to/tests` (this project uses Pest). Record the baseline.
- If the scoped code has thin or no coverage, say so plainly and recommend writing
  characterization tests for the call sites first, or proceed only with the user's
  explicit OK. Note the risk in the final summary either way.

### 2. Detect candidates

Run the scanner to surface the two smells, then apply judgement to its output:

```bash
python scripts/find_candidates.py <scope...> --min-params 4 --min-occurrences 2
```

It flags **long parameter lists** (one signature, many params) and, more importantly,
**data clumps** (the same group of params travelling together across several
signatures). Add `--json` to get machine-readable output. The scanner is a
heuristic — it points; you decide. Read its candidates against the code, because the
strongest signal isn't arity, it's *recurrence*: a trio that appears in six methods is
a concept hiding in plain sight.

### 3. Identify the concept (this is the whole point)

For each surviving candidate, name the concept the cluster represents. The name must
describe **what the data means in the domain**, not its shape or origin:

- `$start, $end` → `DateRange`
- `$street, $city, $postcode, $country` → `Address`
- `$tillId, $openingFloat, $countedCash, $expectedCash` → `TillReconciliation`
- `$lat, $lng` → `Coordinates`
- `$amount, $currency` → `Money`

Litmus test: **if you can't name the concept in one or two crisp domain words, it is
probably not a DTO.** A vague name (`BookingData`, `ServiceParams`, `InfoBag`) is a
signal the cluster isn't a real concept — either it's a coincidental grouping, or the
method does too much and wants splitting instead. Don't force it. Reusing an existing
Eloquent model or an already-defined value object beats inventing a near-duplicate.

### 4. Design the DTO

Match the project's existing conventions first — check `composer.json` for
`spatie/laravel-data`, and look for an existing `app/Data` / `app/DataTransferObjects`
folder and its house style. Then design per **`references/dto-patterns.md`**, which
covers plain `final readonly` classes vs spatie/laravel-data, factory methods
(`fromRequest`, `fromModel`, `fromArray`), collections of DTOs, validation, and where
DTOs live. Read it before writing classes. For a full start-to-finish refactor — and a
companion case where extraction is correctly *declined* — see
**`references/worked-example.md`**; it's the best template for the judgement calls.

Defaults when the project has no established style: a `final readonly` class with
constructor property promotion, immutable, typed, named for the concept, with
factory methods for its real construction paths and constructor-level invariant checks
only. Nothing that mutates or does I/O.

### 5. Refactor incrementally

One concept at a time. For each concept:

1. Create the DTO class.
2. Update the method/constructor signatures in scope to accept it.
3. Update every call site — search the whole codebase, not just the scope, for callers
   (`grep`/`rg` the method names). A signature change ripples outward.
4. Run the tests. Green before starting the next concept.

Keep each concept's change a self-contained, reviewable diff. Preserve nullability,
default values, and validation semantics exactly (step 4 of the reference). Use named
arguments at construction sites so the mapping stays obvious in review.

### 6. Verify and summarize

- Re-run the scoped tests; confirm still green against the step-1 baseline.
- Run static analysis if the project has it (`./vendor/bin/phpstan analyse`, Larastan)
  — DTOs with typed properties usually *improve* the type picture; fix any new findings.
- Summarize: concepts introduced (with one-line rationale each), signatures
  simplified, call sites touched, and anything you deliberately left alone and why.

### 7. Hand off to laravel-simplifier

Once — and only once — the refactor is complete and the scoped tests are green, hand
off to the **`laravel-simplifier`** skill for a follow-up simplification pass over the
**same scope**. Introducing DTOs tends to leave new simplification opportunities in its
wake (call sites that unpacked-then-repacked the same primitives, now-redundant local
variables, mapping code the DTO's factory methods have absorbed), and that pass is
laravel-simplifier's job, not this skill's.

Trigger it by consulting the `laravel-simplifier` skill and running it against the
files you just touched. Do **not** hand off if the work was abandoned, left red, or
only partially applied — finish or revert first, because simplifying on top of a broken
refactor compounds the problem. Mention the handoff in the step-6 summary so the user
knows a second pass is running. If `laravel-simplifier` isn't available in the
environment, say so in the summary and stop cleanly — the DTO work still stands alone.

## Laravel-specific hazards — check these before reshaping a signature

These are the signatures where "just change the constructor" can break things at
runtime or across deploys. Flag them; don't silently rewrite them.

- **Queued jobs & their constructors.** A job's constructor args are *serialized onto
  the queue*. Changing them can break jobs already enqueued during a deploy (Horizon
  will fail to unserialize the old payload). If you DTO-ify a job's args, either ensure
  the queue is drained first, keep the payload shape backward-compatible, or version it.
- **Events & listeners.** Event payloads are serialized for queued listeners and
  broadcast (Reverb). Same in-flight concern as jobs.
- **Livewire component public properties.** Public props are dehydrated to the wire and
  must be primitives or `Wireable`/`Data`. A plain readonly DTO as a public Livewire
  property won't hydrate — use a spatie/laravel-data object (it's `Wireable`) or keep
  it in a method, not a public prop.
- **Filament form/action data.** Schema data and bulk-action payloads are arrays by
  convention; introduce DTOs at the service boundary the form *calls into*, not as the
  form state itself, unless using `Data` objects designed for it.
- **Public API / package boundaries.** Changing a public method signature is a breaking
  change for external callers. Note it; consider an overload-free additive path.
- **Eloquent attribute casts & `$fillable`.** Don't replace model attribute plumbing
  with DTOs; that's a different mechanism (custom casts) — out of scope here.

The two least-obvious hazards, made concrete:

```php
// LIVEWIRE: a plain readonly DTO as a public prop silently fails to hydrate.
class BookingForm extends Component
{
    public DateRange $dates;          // ✗ won't survive a request round-trip
    public DateRangeData $dates;      // ✓ spatie Data is Wireable, so it hydrates
}

// QUEUED JOB: don't reshape the constructor of a job that may have in-flight payloads.
// Add a DTO-accepting path without removing the old shape until the queue has drained.
public function __construct(
    public readonly ?BookingRequestData $request = null,  // new path
    public readonly ?int $userId = null,                  // legacy args kept nullable
    public readonly ?int $venueId = null,                 // for old serialized payloads
) {}
```

## What success looks like

A reviewer reading the diff sees domain concepts appear, signatures shrink, and the
same data stops being re-typed everywhere — with the test suite green at every step and
no anonymous "bag of primitives" classes anywhere in the result.
