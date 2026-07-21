# Spec: Nova → Filament Migration-Spec Generator Skill

**Date**: 2026-07-20  
**Brief**: [Discussion record](../discussions/01-discussion-nova-to-filament-migration-skill.md)

## Problem Summary

Migrating a Laravel Nova admin application to FilamentPHP is high-risk because Nova's functionality is spread across many primitive types (Resources, fields, actions, filters, lenses, metrics, dashboards, cards, tools, policies) and is heavily customised in ways a casual scan misses — field callbacks, computed accessors, conditional visibility, and bespoke Vue components. An incomplete inventory becomes a silent gap in the migrated app. This spec defines a new Claude Code skill (living in `.claude/skills/` in this repo, structurally a sibling of `solid-spec`) that audits an existing Nova installation against a fixed taxonomy, reconciles every audited item into a hard-gated ledger, and emits a cpm-compliant spec mapping every Nova primitive one-to-one onto a specified FilamentPHP version — so downstream `/cpm:epics` receives a complete, accurate, version-stamped migration plan. Note: the artifact this skill *produces* is itself a spec that feeds `/cpm:epics`; this document specifies the skill, not the migrations it generates.

## Functional Requirements

### Must Have

- **FR1 — Invocation & version resolution**: Accept a target Nova app path and a pinned target Filament version (e.g. `^4.0`). Resolve the installed Nova version from the target's `composer.lock`. Refuse to run if no Nova installation is detected.
- **FR2 — Fixed Nova taxonomy checklist**: Walk every Nova primitive category — Resources, all Field types, Actions, Filters, Lenses, all four Metric types (value/trend/partition/progress), Dashboards, Cards, custom Tools, policies/authorization, relationships — and report a count for each, including zero. Completeness is achieved by construction.
- **FR3 — Implementation-depth audit**: For each found primitive, read the implementation body (`fields()`, `Action::handle()`, filter/lens/metric logic, policy methods) and its callbacks (`resolveUsing`/`displayUsing`/`dependsOn`/`canSee`, custom validation, computed accessors). Capture behaviour, not just declarations, with `file:line (symbol)` citations.
- **FR4 — Filament API grounding**: Ground mappings in the pinned Filament version's official documentation, and read the target's installed `vendor/filament` source as the authority when documentation is missing or unclear.
- **FR5 — Reconciliation ledger with hard gate**: Every audited item resolves to exactly one disposition — `direct` / `behaviour-change` / `[rebuild]` / `needs-human`. The skill refuses to finalise the spec while any row is unresolved, and reports disposition counts.
- **FR6 — cpm-compliant spec emission**: Emit a spec at `docs/specifications/{nn}-spec-{slug}.md` modelled on `solid-spec`: one functional requirement per Nova primitive, the reconciliation ledger doubling as the Acceptance Criteria Coverage table, a header stamped with the resolved Nova and pinned Filament versions, and a `[rebuild]` disposition tag added to the vocabulary. Parity criteria are behavioural, not pixel-level.
- **FR7 — Coverage manifest (no silent omissions)**: Report every file scanned and every item that could not be classified, so gaps are visible rather than dropped.
- **FR8 — Tooling discovery**: Attempt discovery of authoritative tooling (Laravel Boost `application-info`/`search-docs`, `composer`, `artisan`) and prefer it when present, while degrading gracefully to `Glob`/`Grep`/`Read` when absent. Mandatory behaviour, not a mandatory dependency.

### Should Have

- **FR9 — Migration sequencing hint**: Propose an order for migrating resources (dependencies/relationships first) so downstream epics inherit a sensible sequence.

### Could Have

- **FR10 — Epic-sizing guidance**: When the audit is large, suggest how the resulting spec breaks into consumably-sized epics.
- **FR11 — UX-drift notes**: For `[rebuild]` items, flag where the Filament rebuild risks diverging from the original Nova UX.

### Won't Have (this iteration)

- Performing the migration or writing any Filament code (that is `/cpm:do` downstream).
- Automated code transformation / codemods.
- Support for non-Nova admin panels (Backpack, etc.).
- Runtime-first (app-booting) audit as a requirement.

## Non-Functional Requirements

- **Accuracy & completeness (primary)**: Zero silent omissions — every taxonomy category reported (even at zero), every found item carrying a disposition.
- **Read-only safety**: Never mutate the target Nova codebase; reads/greps/`composer.lock` inspection only; writes confined to `docs/`.
- **Graceful degradation**: Function without Laravel Boost, without Filament yet installed, and without network access — each absent capability degrades to a documented fallback, never a hard failure.
- **Portability**: Run against an arbitrary target Nova project; all paths resolved relative to the invocation directory.
- **Scale / context safety**: Large Nova apps must not overflow the context window — fan out the audit across primitives (subagent delegation) and batch rather than reading everything into one context.
- **Maintainability**: Nova→Filament mapping knowledge lives in updatable `references/` tables so the skill stays current as Filament evolves without rewriting `SKILL.md`.
- **Determinism**: Re-running against unchanged code produces a materially equivalent, diffable audit (stable taxonomy ordering and citations).

## Architecture Decisions

### AD1 — Skill form: instruction + reference tables (no analyser script)
**Choice**: Build as a prompt-driven `SKILL.md` plus `references/*.md` knowledge tables, like `solid-spec`. No PHP/JS analyser script is shipped. One deliberate, minimal exception: a small structural-check helper (see Testing Strategy) is shipped and reviewed as code.  
**Rationale**: This repo's `CLAUDE.md` flags any skill shipping an executable script/hook for code review. Keeping the audit engine instruction-driven avoids that surface for the bulk of the skill and matches every existing skill here.  
**Alternatives considered**: A full PHP analyser script — rejected: heavier review burden and couples the skill to a PHP runtime being present.

### AD2 — Audit engine: static-first with optional runtime augmentation
**Choice**: Static analysis (fan-out by taxonomy category) is the spine; optional runtime cross-check via Boost/`tinker` when present. Unconfirmed dynamic registrations become `needs-human` rows.  
**Rationale**: A cleanly-bootable app is rarely available mid-migration; static is portable, read-only, and reproducible. Runtime augmentation catches dynamic registrations without gating on an environment.  
**Alternatives considered**: Static-only (misses dynamic registration); runtime-first (authoritative but fragile — requires a bootable app and environment).

### AD3 — Mapping knowledge: bundled per-version tables, grounded by live docs + vendor source
**Choice**: Ship a stable `references/nova-taxonomy.md` plus per-Filament-major mapping tables; the initial set is `nova-to-filament-v5.md` (current major, default) and `nova-to-filament-v4.md` (retained for apps mid-transition still pinning v4). At run time, confirm/correct against the pinned version's live docs and the target's `vendor/filament` source. *(v5 note: per the official v5 announcement, v5 is v4 plus Livewire v4 — the primitive mappings match v4; the only migration-relevant delta is that `[rebuild]` items target Livewire v4.)*  
**Rationale**: Bundled tables make the common case fast and offline; live docs + vendor source keep accuracy against the exact pinned version.  
**Alternatives considered**: Live-only (network-dependent, slow, no offline); bundled-only (rots as Filament evolves).

### AD4 — Output contract: reuse the cpm spec template verbatim
**Choice**: Consume the fixed cpm spec structure (as `solid-spec` does via `references/spec-template.md`); the reconciliation ledger *is* the Acceptance Criteria Coverage table; add a `[rebuild]` tag to the vocabulary.  
**Rationale**: Downstream `/cpm:epics` depends on the exact structure; don't fork the contract.  
**Alternatives considered**: A bespoke migration-report format — rejected: breaks the pipeline handoff.

### AD5 — Reconciliation gate: procedural, with an explicit pre-write self-check
**Choice**: Enforce the "no unresolved rows" gate as a mandatory, non-skippable procedural step — before writing the spec, the skill self-checks the ledger and routes any `needs-human` items through `AskUserQuestion`, then reports disposition counts.  
**Rationale**: A prompt skill cannot compile-enforce a gate; an explicit checklist step plus the shipped structural-check helper provides the safety net.  
**Alternatives considered**: Trust the flow without a self-check — rejected: too easy to emit an incomplete ledger.

## Scope

### In Scope

- The skill: `.claude/skills/nova-to-filament/SKILL.md` + `references/` (taxonomy + at least one target-version mapping table).
- Taxonomy-driven, static-first audit (+ optional runtime cross-check).
- Implementation-depth reading (bodies + callbacks) with `file:line` citations.
- Version resolution (Nova from `composer.lock`) and Filament API grounding (pinned docs + vendor source).
- Reconciliation ledger with the hard gate and disposition counts.
- cpm-compliant spec emission + coverage manifest.
- Tooling discovery (FR8) and migration sequencing hint (FR9).
- A small structural-check helper validating emitted-spec invariants.
- Updating the README "Included skills" table when the skill lands (repo convention).

### Out of Scope

- Performing the migration or writing any Filament code.
- Automated code transformation / codemods.
- Non-Nova admin panels.
- Runtime-first (app-booting) audit as a requirement.

### Deferred

- FR10 epic-sizing guidance.
- FR11 `[rebuild]` UX-drift notes (named and reserved).
- Mapping tables for Filament versions beyond the initially-targeted major(s).

## Testing Strategy

### Tag Vocabulary
Test approach tags used in this spec:

- `[unit]` — Unit tests targeting individual components in isolation
- `[integration]` — Integration tests exercising boundaries between components
- `[feature]` — Feature/end-to-end tests exercising complete user-facing workflows
- `[manual]` — Manual inspection, observation, or user confirmation
- `[tdd]` — Workflow mode: task follows red-green-refactor loop. Composable with any level tag (e.g. `[tdd] [unit]`). Orthogonal — describes how to work, not what kind of test.

> Note: `[rebuild]` is a **disposition tag in the skill's output specs**, not a test-approach tag. The tags above verify the *skill itself*. Because the deliverable is model-generated analysis, most criteria are `[manual]` (justification: no deterministic code path exists to assert LLM output — verified by inspecting the emitted spec). The gate's structural invariants are the automatable exception, backed by the shipped structural-check helper.

### Acceptance Criteria Coverage

| Requirement | Acceptance Criterion | Test Approach |
|---|---|---|
| FR1 Invocation & versions | Resolves exact Nova version from `composer.lock`; stamps Nova + pinned Filament versions in the spec header | `[manual]` |
| FR1 Invocation & versions | **must NOT** emit a spec when no Nova install is detected at the target | `[manual]` |
| FR2 Taxonomy checklist | Emitted manifest lists every Nova taxonomy category with a count, including zero-count categories | `[manual]` |
| FR2 Taxonomy checklist | **must NOT** silently omit any taxonomy category | `[manual]` |
| FR3 Impl-depth audit | Records field/action callback, computed, and conditional behaviour with `file:line` citations — not just the declared type | `[manual]` |
| FR3 Impl-depth audit | **must NOT** classify a customised item as `direct` when its behaviour has no direct Filament equivalent | `[manual]` |
| FR4 API grounding | Dispositions reflect the pinned Filament version's API; installed `vendor/filament` source overrides docs on conflict | `[manual]` |
| FR5 Ledger + gate | Every audited item has exactly one disposition; disposition counts are reported | `[integration]` |
| FR5 Ledger + gate | **must NOT** finalise/write the spec while any ledger row is unresolved | `[integration]` |
| FR6 Spec emission | Output conforms to the cpm spec template (headings, MoSCoW, AC coverage table, `[rebuild]` in vocabulary); one FR per Nova primitive | `[integration]` |
| FR6 Spec emission | Parity criteria are behavioural, not pixel-level | `[manual]` |
| FR7 Coverage manifest | Lists all files scanned and all unclassified items | `[manual]` |
| FR8 Tooling discovery | Attempts discovery of Boost/composer/artisan and uses it when present; runs to completion with none present | `[manual]` |

### Integration Boundaries

- **Skill ↔ target Nova codebase** (read-only file + `composer.lock` access): the audit correctness boundary.
- **Skill ↔ Filament knowledge** (bundled tables / live docs / `vendor/filament`): the mapping-accuracy boundary.
- **Skill ↔ cpm spec contract**: emitted spec must conform for `/cpm:epics` to consume — the highest-value boundary; backed by the structural-check helper.
- **Skill ↔ optional tooling** (Boost/tinker): must degrade gracefully when absent.

### Test Infrastructure
**None built this iteration** (deliberate). The skill's true verification requires driving one complete Nova→Filament transformation; a purpose-built fixture app or harness cannot be proven correct before that. The skill will therefore be **validated and refined retrospectively after its first full lifecycle use** — the first production run doubles as the skill's acceptance test for the `[manual]` criteria. The only automated safety net shipped now is the structural-check helper (a skill deliverable, not test infrastructure) validating emitted-spec invariants — no empty disposition cells, all taxonomy categories present, version header stamped.

### Unit Testing
Unit testing of individual components is handled at the `cpm:do` task level — each story's acceptance criteria drive test coverage during implementation. For this prompt skill, that is limited to the structural-check helper.
