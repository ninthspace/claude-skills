# Coverage Matrix: Reconciliation & Emission

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Epic**: docs/epics/01-03-epic-reconciliation-and-emission.md  
**Date**: 2026-07-20

> **Verification rule**: Verification status (✓) is bound to criterion text. Any change to a story criterion or its spec mapping resets that row to unverified.

| # | Spec Requirement | Spec Text (verbatim) | Story Criterion (verbatim) | Covered by | Spec Test Approach | Verified |
|---|------------------|----------------------|----------------------------|------------|--------------------|----------|
| 1 | FR5 Reconciliation ledger with hard gate | "Every audited item resolves to exactly one disposition — `direct` / `behaviour-change` / `[rebuild]` / `needs-human`." | "Every audited item resolves to exactly one disposition — `direct` / `behaviour-change` / `[rebuild]` / `needs-human`" | Story 1 | `[integration]` | ✓ |
| 2 | FR5 Reconciliation ledger with hard gate | "…and reports disposition counts." | "Disposition counts are reported (e.g. \"142 direct, 9 behaviour-change, 4 rebuild, 2 needs-human\")" | Story 1 | `[integration]` | ✓ |
| 3 | FR5 Reconciliation ledger with hard gate | (must-NOT) "**must NOT** finalise/write the spec while any ledger row is unresolved" | "must NOT finalise/write the spec while any ledger row is unresolved" | Story 1 | `[integration]` | ✓ |
| 4 | FR6 cpm-compliant spec emission | "Emit a spec at `docs/specifications/{nn}-spec-{slug}.md` modelled on `solid-spec`: one functional requirement per Nova primitive, the reconciliation ledger doubling as the Acceptance Criteria Coverage table, a header stamped with the resolved Nova and pinned Filament versions, and a `[rebuild]` disposition tag added to the vocabulary." | "Emits a spec at `docs/specifications/{nn}-spec-{slug}.md` following the cpm spec template (headings, MoSCoW, Acceptance Criteria Coverage table, tag vocabulary)" | Story 2 | `[integration]` | ✓ |
| 5 | FR6 cpm-compliant spec emission | "…one functional requirement per Nova primitive, the reconciliation ledger doubling as the Acceptance Criteria Coverage table…" | "One functional requirement per Nova primitive; the reconciliation ledger doubles as the Acceptance Criteria Coverage table" | Story 2 | `[integration]` | ✓ |
| 6 | FR6 cpm-compliant spec emission | "…a header stamped with the resolved Nova and pinned Filament versions, and a `[rebuild]` disposition tag added to the vocabulary." | "Header stamped with the resolved Nova version and the pinned Filament version" / "A `[rebuild]` disposition tag is added to the tag vocabulary" | Story 2 | `[integration]` | ✓ |
| 7 | FR6 cpm-compliant spec emission | "Parity criteria are behavioural, not pixel-level." | "Parity criteria are behavioural, not pixel-level" | Story 2 | `[manual]` | ✓ |
| 8 | FR7 Coverage manifest | "Report every file scanned and every item that could not be classified, so gaps are visible rather than dropped." | "Reports every file scanned during the audit" / "Reports every item that could not be classified (unclassified / `needs-human`) so gaps are visible rather than dropped" | Story 3 | `[manual]` | ✓ |
| 9 | FR9 Migration sequencing hint | "Propose an order for migrating resources (dependencies/relationships first) so downstream epics inherit a sensible sequence." | "Proposes an order for migrating resources with dependencies/relationships first" / "The sequencing hint appears in the emitted spec so downstream epics inherit an order" | Story 4 | *(should-have; untagged in spec)* | ✓ |
