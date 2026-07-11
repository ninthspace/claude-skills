---
name: solid-spec
description: Survey PHP, Laravel, and JavaScript code (including JS embedded inside HTML/Blade templates) for SOLID violations, then write a cpm-compliant specification at docs/specifications/{nn}-spec-{slug}.md scoping the rework. Use whenever the user asks for a SOLID refactor spec, mentions god classes / fat controllers / switch-on-type / "this class does too much" / dependency injection cleanup / extract service / "make this testable", invokes /solid-spec, or wants a refactor plan that downstream cpm skills (epics, do, ralph) can pick up. Always activate when the user asks to "plan a SOLID refactor" or to "write a spec for refactoring X" — even when SOLID is implied rather than named, this is the right tool.
---

# SOLID Refactor Spec

Sweep the codebase for SOLID violations across PHP, Laravel, and JavaScript (including JS embedded in `.html` and `.blade.php` files), then write a specification at `docs/specifications/{nn}-spec-{slug}.md` that downstream `cpm:epics` / `cpm:do` can execute.

The output is a **cpm:spec compliant document** — not a free-form refactoring plan. Section headings, MoSCoW grouping, test tag vocabulary, and the Acceptance Criteria Coverage table are all fixed by the cpm contract. See `references/spec-template.md` for the exact structure to emit.

## Why this skill exists

`/cpm:spec` is conversation-driven and assumes the user already knows what they want built. SOLID refactors run the other way: the *code* tells you what's wrong, and the spec is the synthesis of those observations. This skill does the analytical sweep first (like `/cpm:audit`) and emits a spec directly so the cycle continues into `cpm:epics` without a second round of facilitation.

## Input

Parse `$ARGUMENTS` in this order:

1. **File or directory path** — e.g. `/solid-spec app/Models/Note.php` or `/solid-spec resources/views/livewire/`. The sweep is scoped to that target plus its direct collaborators (controllers/components that import it, traits it uses).
2. **Symbol or topic hint** — e.g. `/solid-spec auth` or `/solid-spec checkout flow`. Resolve to a directory or set of files via Glob/Grep before sweeping.
3. **Empty** — sweep the active changeset first (`git diff --name-only HEAD~5..HEAD` plus `git status --porcelain`) and ask the user with `AskUserQuestion` whether to (a) target that changeset, (b) target a specific path they name, or (c) sweep the whole `app/`, `resources/`, and `public/js/` (or equivalent) tree.

Empty `$ARGUMENTS` should never silently fall back to a whole-repo sweep — that produces specs too large to land as one cycle. Confirm scope first.

## Process

### Step 1: Stack detection and scope confirmation

Detect what languages and frameworks are in play. The sweep heuristics differ per stack:

- `composer.json` → PHP. If `artisan` is also present, layer Laravel-specific heuristics on top (controllers, models, form requests, jobs, service providers, Livewire/Volt components, Blade).
- `package.json` → JavaScript / TypeScript.
- Any `.blade.php` or `.html` file containing `<script>` blocks → JS-in-template scan.

Multi-stack projects run every applicable heuristic set. Record detected stacks in the progress file.

After detection, present scope to the user via `AskUserQuestion` if it wasn't pre-supplied: "Sweep {n} files across {stacks}? Or narrow further?" Options: `Proceed` / `Narrow` / `Cancel`.

### Step 1b: Tooling discovery (self-discovered, optional)

Before starting the sweep, check what tooling is available — none of these are required, but each one tightens the sweep when present.

- **`mcp__laravel-boost__*`** — if the project has Laravel Boost installed, prefer its tools over manual alternatives. The most useful for this skill are:
  - `application-info` — confirms Laravel/PHP version + installed packages, so recommendations don't propose features unavailable in the version actually in use.
  - `database-schema` — read the real columns before recommending `$fillable` narrowing, cast changes, scope rewrites, or value-object extractions.
  - `database-query` — read-only queries to sanity-check assumptions (e.g. "is this nullable column ever actually null in production-shaped data?").
  - `search-docs` — version-pinned Laravel docs lookup. Use it before drafting an Architecture Decision that references a Laravel pattern (observers, action classes, scopes, casts, form request authorisation, container binding) so the rationale and phrasing match the docs the project's version ships with.
  - `last-error` / `read-log-entries` / `browser-logs` — useful when the SOLID violation is being motivated by an active bug; the error often points straight at the smell (god-class stack traces, swallowed exceptions, etc.).
  - `tinker` — for quickly probing call sites or model state during the sweep when grep alone is ambiguous. Read-only intent; never use it to mutate data.
- **`larastan` / `phpstan`** (via project config) — if the project runs static analysis, scan its baseline output for "ignored" files. Files in the baseline are often god classes the project gave up on; they're high-yield sweep targets.
- **`pint --test`** — reveals consistency drift (formatting, idiomatic patterns) which often correlates with SRP violations. Don't run `pint` itself during the sweep (it mutates), but the baseline of "files Pint would change" is informative.
- **`composer show -i`** + **`php artisan about`** — when boost isn't installed, these are the manual fallbacks for stack/version detection.

The sweep MUST NOT depend on any of these tools being present — when boost isn't installed, fall back to `Glob`, `Grep`, and `Read` as usual. Tooling discovery is for sharpening the sweep, never for gating it.

When you do use a boost tool to inform a finding, mention it implicitly in the recommendation phrasing (e.g. "the cast violates the actual schema — `body` is `LONGTEXT` per `database-schema`, not the JSON shape the cast assumes"). Don't list tool calls as evidence in the spec body — the spec stays code-focused.

### Step 2: SOLID sweep

Sweep the in-scope code for violations of each SOLID principle. Capture every concrete finding as a `file:line (symbol)` citation — no hand-waving. The sweep is reads-only.

**Principle order** (fixed — keep findings grouped by principle in the working notes):

1. **S**ingle Responsibility (SRP)
2. **O**pen/Closed (OCP)
3. **L**iskov Substitution (LSP)
4. **I**nterface Segregation (ISP)
5. **D**ependency Inversion (DIP)

For each principle, consult the stack-specific heuristics:

- **PHP and Laravel** → `references/solid-php-laravel.md`. Covers fat controllers, god models, Action / Service extraction, switch-on-type vs polymorphism, facade injection, observer vs `booted()`, queryable scopes vs raw `where`, form-request authorisation, etc.
- **JavaScript (incl. embedded in HTML/Blade)** → `references/solid-javascript.md`. Covers monolithic components, inline `<script>` blocks doing logic, jQuery-style DOM mutation that should be event-driven, switch-on-type vs strategy maps, hard-coded module dependencies vs injection, prop-drilling vs composition, etc.

When scanning Blade/HTML files, treat each `<script>` block as JavaScript and apply the JS heuristics. Inline event handlers (`onclick="…"`, `wire:click="…"` calling JS via `$wire.dispatch`, `x-on:click=`) count as JS too — note the host template path and approximate line of the script block.

Every finding records:

- `file:line (symbol)` citation — `(symbol)` may be a class, function, Livewire component method, Alpine `x-data` block, or `<script>#N` for embedded blocks.
- Which SOLID principle is violated.
- A one-sentence diagnosis of what's wrong.
- A scoped rewrite recommendation (extract X to Y, replace switch with strategy, inject Z, etc.) — **no full-rewrite phrasing**, the same rule cpm:audit uses.

### Step 3: Synthesis — findings → requirements

Group related findings into spec **requirements**. A requirement is the unit `cpm:epics` consumes, so they should be:

- **Cohesive** — one requirement = one refactor that lands as one PR. Don't bundle "extract NoteFileCleaner" with "narrow $fillable" into one R; they're independent changes.
- **Citable** — every requirement names the specific file(s) and symbol(s) it touches, drawn from the findings.
- **Testable** — every must-have requirement has at least one acceptance criterion that maps to `[unit]`, `[integration]`, `[feature]`, or (rarely) `[manual]`.

Map findings to MoSCoW buckets:

- **Must Have**: SOLID violations that actively cause bugs, block other work, or appear in hot-path code that the user is currently iterating on.
- **Should Have**: structural improvements with clear payoff but no blocking dependency.
- **Could Have**: tidy-ups discovered in passing — small extractions, DPI cleanups in cold paths.
- **Won't Have**: violations the sweep found but that are out of scope for this cycle (note them so the user sees they were considered and intentionally deferred).

If the sweep produces more than ~10 must-haves, the scope is too wide. Either narrow it via `AskUserQuestion` or split must-haves down to the top 5–7 and demote the rest to should-haves.

### Step 4: Architecture decisions

For each must-have that involves a non-trivial structural choice, draft an `AD-N` block with:

- **Choice**: what's chosen (e.g. "Extract as `App\Services\Notes\NoteFileCleaner` with constructor injection").
- **Rationale**: why this shape over alternatives. Lean on existing project conventions discovered during the sweep.
- **Alternatives considered**: at least one other shape the user might propose (e.g. trait, action class, repository) and why it was rejected.

If the codebase has consistent conventions (e.g. `App\Services\{Domain}\` namespacing, action classes in `app/Actions/`), reference them in rationales rather than inventing new structure. Drift from existing patterns is itself a SOLID violation worth flagging.

### Step 5: Testing strategy

Produce the **Acceptance Criteria Coverage** table. For each must-have requirement, derive one or more criteria from the corresponding findings and tag each with the test approach.

Default to automation — pick `[manual]` only when verification genuinely can't be automated (visual judgement, third-party UI, content review). For SOLID work specifically:

- Class extraction / responsibility moves → `[unit]` for the new collaborator + `[integration]` for the consumer.
- Polymorphism replacing switch-on-type → `[unit]` per concrete strategy + arch test if the project enforces "no switch on type X" elsewhere.
- DI cleanup (removing facades/`new` calls deep in business logic) → `[unit]` proving the collaborator can be substituted.
- JS module extraction from a Blade `<script>` → `[feature]` (Pest browser) covering the user-visible behaviour the script powers, since JS-in-template is hard to unit-test.

Surface any **test infrastructure gaps** the refactor surfaces — e.g. "no Pest browser test exists for the dashboard JS this story extracts; story includes scaffolding the first browser test". These become stories in `cpm:epics`.

### Step 6: Write the spec

Save the document to `docs/specifications/{nn}-spec-{slug}.md`.

- `{nn}` — find the highest existing number across `docs/specifications/*.md` and use the next integer, zero-padded to 2 digits. Numbers stay retired (don't reuse gaps from archived specs).
- `{slug}` — kebab-case, derived from the scope hint or the dominant subject of the must-haves. Keep it under 5 words. Examples: `note-model-debt-remediation`, `auth-middleware-srp-fix`, `dashboard-script-extraction`.

Create `docs/specifications/` if it doesn't exist.

Use the structure in `references/spec-template.md` exactly — section headings, table columns, and tag vocabulary are downstream contracts. Don't paraphrase them.

### Step 7: Review gate

After writing, render a short summary in the chat (title, requirement count, file list) and ask via `AskUserQuestion`: `Approve this spec?` with options `Approve` / `Request changes` / `Cancel and discard`.

- **Approve** → suggest next steps: `/cpm:epics docs/specifications/{nn}-spec-{slug}.md` to break it down, or `/cpm:do` if the user wants to start straight from a single must-have.
- **Request changes** → ask which section(s) to revise, edit in place, re-render the summary.
- **Cancel and discard** → delete the spec file. Confirm before deleting.

## Guidelines

- **Cite, don't quote.** Every finding has `file:line (symbol)`. Never paste large code blocks into the spec — the spec points at the code, the code stays the source of truth.
- **No-rewrites rule** (inherited from `cpm:audit`). Recommendations describe scoped extractions and substitutions. The phrases "rewrite", "rebuild", "ground-up", "from scratch" are forbidden in the spec body. If something genuinely needs a rewrite, the spec captures the symptoms and stops there — that's a `/cpm:architect` conversation.
- **One refactor per requirement.** Resist the temptation to bundle "while we're in there". Each R should land as its own PR; bundling makes code review and rollback harder.
- **Existing conventions win.** If the project already uses action classes for one-shots, propose action classes — don't invent service classes. The sweep should surface conventions before the synthesis phase relies on them.
- **JS-in-template counts.** Inline `<script>` blocks, Alpine `x-data` blocks with multi-step logic, and event-handler attributes are all in scope. They're often the worst SOLID offenders precisely because they're "just a few lines of glue".
- **Trust the user's stop signal.** If the user says "stop, I just want R3 and R5", drop the others to Should-Have or Won't-Have rather than arguing for completeness.

## State Management

Keep a progress file at `docs/plans/.solid-spec-progress.md` while the sweep is in flight (create `docs/plans/` if it doesn't exist). Delete it once the spec is written and approved.

Format:

```markdown
# solid-spec session

**Scope**: {resolved scope}
**Stacks detected**: {list}
**Current step**: {sweep | synthesis | spec-write | review}

## Findings so far
{Bulleted list of file:line (symbol) — principle — diagnosis, grouped by principle}

## Pending decisions
{Open questions to put to the user}
```

The progress file lets compaction resume mid-sweep without losing the citations already gathered.
