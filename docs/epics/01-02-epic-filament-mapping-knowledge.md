# Filament Mapping Knowledge

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Date**: 2026-07-20  
**Status**: Complete  
**Blocked by**: Epic 01-01-epic-audit-engine-taxonomy

## Author the Nova→Filament mapping tables (per Filament major version)
**Story**: 1  
**Status**: Complete  
**Blocked by**: —  
**Satisfies**: FR4

**Acceptance Criteria**:

- `references/` contains per-Filament-major mapping tables (e.g. `nova-to-filament-v4.md`) mapping each Nova primitive from the taxonomy to its Filament construct and a disposition class (`direct` / `behaviour-change` / `[rebuild]`) [manual]
- Every Nova taxonomy category has at least one row in each shipped mapping table (no primitive left unmapped in the reference) [manual]
- Bundled mapping tables function offline — the common case needs no network [manual]

### Author references/nova-to-filament-v4.md
**Task**: 1.1  
**Description**: Per-primitive mapping plus disposition class — the offline knowledge base backing AD3. Author `-v3.md` too if v3 is in the initial target set.  
**Status**: Complete

**Scope addendum (2026-07-20)**: After Story 1 first completed, Filament **v5** was identified as the current major. Added `references/nova-to-filament-v5.md` as the primary/default table (grounded from live v5 docs: form components `Filament\Forms\Components`, schema/layout `Filament\Schemas\Components`, and the v5 announcement confirming v5 = v4 + Livewire v4, no core API changes). `references/nova-to-filament-v4.md` retained for apps mid-transition. Both tables cross-checked against the taxonomy (every category ≥1 row). Spec AD3 premise updated accordingly.

### Cross-check the mapping tables against the taxonomy
**Task**: 1.2  
**Description**: Verify every category in `references/nova-taxonomy.md` has at least one mapping row; closes the "no primitive left unmapped in the reference" boundary.  
**Status**: Complete

**Retro**: [Smooth delivery] Story 1 delivered as planned — authoring `nova-to-filament-v4.md` category-by-category against the taxonomy made the cross-check trivial (1:1 by construction). The v4 structural notes (fields split into columns+components, unified Actions namespace, Schemas) pre-empt the classic "one Nova field = one Filament thing" mismapping.

---

## Live-docs + vendor-source grounding rules
**Story**: 2  
**Status**: Complete  
**Blocked by**: Story 1  
**Satisfies**: FR4

**Acceptance Criteria**:

- At run time, mappings are confirmed/corrected against the pinned Filament version's official documentation [manual]
- When the target has Filament installed, `vendor/filament` source is read as the authority and overrides docs on conflict [manual]
- When docs are missing/unclear and no installed source is available, the item is recorded as `needs-human` rather than guessed [manual]

### Add the grounding section to SKILL.md
**Task**: 2.1  
**Description**: Precedence (installed `vendor/filament` source > live docs > bundled table) and conflict resolution — FR4 plus AD3.  
**Status**: Complete

### Define the needs-human fallback for ungroundable items
**Task**: 2.2  
**Description**: When neither docs nor installed source resolve a mapping, record the item as `needs-human` rather than guess; feeds the reconciliation ledger in Epic 01-03.  
**Status**: Complete

**Retro**: [Pattern worth reusing] The three-tier precedence (installed `vendor/filament` source > live docs > bundled table) with `needs-human` as the honest floor is the reusable spine for any "map a known API onto a versioned target" skill — the bundled table keeps the common case offline while the higher tiers prevent fabricated mappings. Reuse when grounding any offline knowledge base against a live, version-pinned authority.

---
