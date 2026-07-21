# Coverage Matrix: Structural-Check Helper & Repo Integration

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Epic**: docs/epics/01-04-epic-helper-and-repo-integration.md  
**Date**: 2026-07-20

> **Verification rule**: Verification status (✓) is bound to criterion text. Any change to a story criterion or its spec mapping resets that row to unverified.

| # | Spec Requirement | Spec Text (verbatim) | Story Criterion (verbatim) | Covered by | Spec Test Approach | Verified |
|---|------------------|----------------------|----------------------------|------------|--------------------|----------|
| 1 | FR5 Reconciliation ledger with hard gate | "The skill refuses to finalise the spec while any row is unresolved, and reports disposition counts." | "A helper (bash/grep) validates that an emitted spec's reconciliation ledger has no empty disposition cells" | Story 1 | `[integration]` | ✓ |
| 2 | FR6 cpm-compliant spec emission | "Emit a spec at `docs/specifications/{nn}-spec-{slug}.md`… following the cpm spec template… a header stamped with the resolved Nova and pinned Filament versions…" | "Validates that the emitted spec header carries the Nova + Filament version stamp" / "Validates the emitted spec conforms to the cpm spec template structure (required headings, AC coverage table present)" | Story 1 | `[integration]` | ✓ |
| 3 | FR2 Fixed Nova taxonomy checklist | "…report a count for each, including zero. Completeness is achieved by construction." | "Validates that every taxonomy category is present in the emitted spec/manifest" | Story 1 | `[integration]` | ✓ |

> Story 2 (skill packaging & README "Included skills" table update, structural-check helper packaging) covers the spec's **Scope** deliverables rather than numbered functional requirements, so it has no requirement rows here.
