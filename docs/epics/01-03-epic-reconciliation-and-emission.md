# Reconciliation & Emission

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Date**: 2026-07-20  
**Status**: Complete  
**Blocked by**: Epic 01-01-epic-audit-engine-taxonomy, Epic 01-02-epic-filament-mapping-knowledge

> **Note**: The `[integration]` criteria in Stories 1 and 2 are automatically verified by the structural-check helper shipped in Epic 01-04 (`Blocked by` this epic). No per-story testing tasks are duplicated here; that automated verification is consolidated into 01-04.

## Reconciliation ledger with the hard gate
**Story**: 1  
**Status**: Complete  
**Blocked by**: —  
**Satisfies**: FR5

**Acceptance Criteria**:

- Every audited item resolves to exactly one disposition — `direct` / `behaviour-change` / `[rebuild]` / `needs-human` [integration]
- Disposition counts are reported (e.g. "142 direct, 9 behaviour-change, 4 rebuild, 2 needs-human") [integration]
- must NOT finalise/write the spec while any ledger row is unresolved [integration]
- The pre-write self-check routes any `needs-human` items through `AskUserQuestion` before finalising [manual]

### Add the reconciliation-ledger section to SKILL.md
**Task**: 1.1  
**Description**: Two-column ledger assigning exactly one of four dispositions per audited item; the FR5 core structure that every audited item flows into.  
**Status**: Complete

### Add the hard gate + pre-write self-check + disposition counts
**Task**: 1.2  
**Description**: AD5 — covers the must-NOT (no unresolved rows), disposition count reporting, and routing any `needs-human` items through `AskUserQuestion` before the spec is finalised.  
**Status**: Complete

**Retro**: [Pattern worth reusing] Making the reconciliation ledger *balance against the census* (row count == census count per category) turns "did we drop anything?" into an arithmetic check rather than a judgement call. The same reconcile-to-total discipline recurs from the fan-out audit through the ledger to the coverage manifest — one invariant enforced at every stage is what makes the completeness claim provable end-to-end.

---

## cpm-compliant spec emission
**Story**: 2  
**Status**: Complete  
**Blocked by**: Story 1  
**Satisfies**: FR6

**Acceptance Criteria**:

- Emits a spec at `docs/specifications/{nn}-spec-{slug}.md` following the cpm spec template (headings, MoSCoW, Acceptance Criteria Coverage table, tag vocabulary) [integration]
- One functional requirement per Nova primitive; the reconciliation ledger doubles as the Acceptance Criteria Coverage table [integration]
- Header stamped with the resolved Nova version and the pinned Filament version [integration]
- A `[rebuild]` disposition tag is added to the tag vocabulary [integration]
- Parity criteria are behavioural, not pixel-level [manual]

### Add the spec-emission section reusing the cpm spec-template contract
**Task**: 2.1  
**Description**: AD4 — one FR per Nova primitive; the reconciliation ledger rendered as the Acceptance Criteria Coverage table; `[rebuild]` added to the tag vocabulary; do not fork the contract.  
**Status**: Complete

### Add version-stamping of the emitted spec header
**Task**: 2.2  
**Description**: Stamp the resolved Nova version and the pinned Filament version into the emitted spec header; covers the version-header structural criterion.  
**Status**: Complete

**Retro**: [Pattern worth reusing] Shipping a bundled `references/spec-template.md` (mirroring the `solid-spec` precedent) and *appending* the Disposition column after the three canonical cpm columns let the reconciliation ledger and the Acceptance Criteria Coverage table be one artifact **without forking the downstream contract** — the parser still finds Requirement/Criterion/Tag in place. Reuse when a skill must extend a fixed output contract: add trailing, don't reorder.

---

## Coverage manifest
**Story**: 3  
**Status**: Complete  
**Blocked by**: Story 1  
**Satisfies**: FR7

**Acceptance Criteria**:

- Reports every file scanned during the audit [manual]
- Reports every item that could not be classified (unclassified / `needs-human`) so gaps are visible rather than dropped [manual]

### Add the coverage-manifest section to SKILL.md
**Task**: 3.1  
**Description**: Lists every file scanned and every unclassified item; the FR7 no-silent-omissions guarantee that sits alongside the emitted spec.  
**Status**: Complete

**Retro**: [Smooth delivery] The coverage manifest fell straight out of the census + ledger already built in Stories 1–2 — file list, category census, disposition tally, and could-not-classify section are all views over data the audit already produced. No new machinery, just an honest companion artifact.

---

## Migration sequencing hint
**Story**: 4  
**Status**: Complete  
**Blocked by**: Story 2  
**Satisfies**: FR9

**Acceptance Criteria**:

- Proposes an order for migrating resources with dependencies/relationships first [manual]
- The sequencing hint appears in the emitted spec so downstream epics inherit an order [manual]

### Add the sequencing-hint section to SKILL.md
**Task**: 4.1  
**Description**: Order resources by relationship dependencies (dependencies/relationships first) and include the ordering in the emitted spec so downstream `/cpm:epics` inherits a sensible sequence.  
**Status**: Complete

**Retro**: [Smooth delivery] The sequencing hint reused the relationship edges already captured in taxonomy §9 — a topological sort over data the audit had in hand. The one non-obvious case (cycles) was handled explicitly rather than assumed away, keeping the hint honest for real Nova apps with circular BelongsTo references.

---
