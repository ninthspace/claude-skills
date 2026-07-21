# Audit Engine & Nova Taxonomy

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Date**: 2026-07-20  
**Status**: Complete  
**Blocked by**: —

## Scaffold the skill and resolve invocation inputs & versions
**Story**: 1  
**Status**: Complete  
**Blocked by**: —  
**Satisfies**: FR1

**Acceptance Criteria**:

- Skill exists at `.claude/skills/nova-to-filament/SKILL.md` with valid frontmatter (name, description with trigger phrases) [manual]
- Accepts a target Nova app path and a pinned target Filament version (e.g. `^4.0`) [manual]
- Resolves the installed Nova version from the target's `composer.lock` and records it for stamping [manual]
- must NOT emit a spec when no Nova install is detected at the target [manual]

### Create skill folder + SKILL.md skeleton
**Task**: 1.1  
**Description**: Establishes the container and FR1 invocation contract — skill folder `.claude/skills/nova-to-filament/`, SKILL.md frontmatter (name, description with trigger phrases), and purpose section.  
**Status**: Complete

### Define input parsing & version resolution
**Task**: 1.2  
**Description**: Covers FR1 happy path and the must-NOT — parse target path + pinned Filament version, resolve Nova version from `composer.lock`, refuse to proceed when no Nova install is detected.  
**Status**: Complete

**Retro**: [Smooth delivery] Story 1 delivered as planned — the skill scaffold and the invocation / version-resolution / no-Nova-guard sections were well-scoped by the epic, no surprises.

---

## Define the fixed Nova taxonomy checklist
**Story**: 2  
**Status**: Complete  
**Blocked by**: —  
**Satisfies**: FR2

**Acceptance Criteria**:

- `references/nova-taxonomy.md` enumerates every Nova primitive category: Resources, all Field types, Actions, Filters, Lenses, all four Metric types (value/trend/partition/progress), Dashboards, Cards, custom Tools, policies/authorization, relationships [manual]
- The audit walks every category and reports a count for each, including zero-count categories [manual]
- must NOT silently omit any taxonomy category [manual]

### Author references/nova-taxonomy.md
**Task**: 2.1  
**Description**: The canonical primitive list plus what to look for per category — the completeness backbone that makes coverage by construction.  
**Status**: Complete

### Add the taxonomy-walk section to SKILL.md
**Task**: 2.2  
**Description**: Iterates every taxonomy category and reports per-category counts; covers count-even-zero and the must-NOT silent-omission boundary.  
**Status**: Complete

**Retro**: [Smooth delivery] Story 2 delivered as planned — the fixed taxonomy reference and the count-every-category walk fell straight out of the design; the "things that hide" section pre-empts the classic incomplete-inventory failure.

---

## Implementation-depth audit of found primitives
**Story**: 3  
**Status**: Complete  
**Blocked by**: Story 2  
**Satisfies**: FR3

**Acceptance Criteria**:

- For each found primitive, reads the implementation body (`fields()`, `Action::handle()`, filter/lens/metric logic, policy methods) and callbacks (`resolveUsing`/`displayUsing`/`dependsOn`/`canSee`, custom validation, computed accessors) [manual]
- Records behaviour, not just the declared type, with `file:line (symbol)` citations [manual]
- must NOT classify a customised item as `direct` when its behaviour has no direct Filament equivalent [manual]

### Add the implementation-depth audit section to SKILL.md
**Task**: 3.1  
**Description**: Reads bodies and callbacks and captures behaviour over declarations with `file:line (symbol)` citations; the must-NOT is enforced jointly with reconciliation in Epic 01-03.  
**Status**: Complete

### Define the context-safety fan-out approach
**Task**: 3.2  
**Description**: Subagent delegation / batching per primitive so large Nova apps don't overflow the context window (the scale/context-safety NFR).  
**Status**: Complete

**Retro**: [Pattern worth reusing] The fan-out-then-reconcile-to-census pattern (parallel sub-agents return structured findings, merged back against the census so findings-count == census-count per category) keeps context bounded while proving nothing was dropped — reuse it for any large read-only codebase audit.

---

## Tooling discovery & graceful degradation
**Story**: 4  
**Status**: Complete  
**Blocked by**: —  
**Satisfies**: FR8

**Acceptance Criteria**:

- Attempts discovery of authoritative tooling (Laravel Boost `application-info`/`search-docs`, `composer`, `artisan`) and prefers it when present [manual]
- Runs to completion using `Glob`/`Grep`/`Read` fallbacks when tooling is absent (graceful degradation) [manual]
- Optional runtime cross-check (Boost/`tinker`) is used when available; unconfirmed dynamic registrations are recorded as `needs-human` [manual]

### Add the tooling-discovery section to SKILL.md
**Task**: 4.1  
**Description**: Probe order, prefer-when-present, and `Glob`/`Grep`/`Read` fallback — FR8 mandatory behaviour, not a mandatory dependency.  
**Status**: Complete

### Add optional runtime cross-check + needs-human routing
**Task**: 4.2  
**Description**: AD2 static-first augmentation — optional Boost/`tinker` cross-check; unconfirmed dynamic registrations flow to reconciliation (Epic 01-03) as `needs-human`.  
**Status**: Complete

**Retro**: [Smooth delivery] Story 4 delivered as planned — static-first with optional runtime cross-check mapped cleanly onto a probe-order + graceful-fallback section; the needs-human routing gives the audit an honest escape hatch for what static analysis can't confirm.

---
