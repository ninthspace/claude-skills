# Coverage Matrix: Filament Mapping Knowledge

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Epic**: docs/epics/01-02-epic-filament-mapping-knowledge.md  
**Date**: 2026-07-20

> **Verification rule**: Verification status (✓) is bound to criterion text. Any change to a story criterion or its spec mapping resets that row to unverified.

| # | Spec Requirement | Spec Text (verbatim) | Story Criterion (verbatim) | Covered by | Spec Test Approach | Verified |
|---|------------------|----------------------|----------------------------|------------|--------------------|----------|
| 1 | FR4 Filament API grounding | "Ground mappings in the pinned Filament version's official documentation…" | "At run time, mappings are confirmed/corrected against the pinned Filament version's official documentation" | Story 2 | `[manual]` | ✓ |
| 2 | FR4 Filament API grounding | "…and read the target's installed `vendor/filament` source as the authority when documentation is missing or unclear." | "When the target has Filament installed, `vendor/filament` source is read as the authority and overrides docs on conflict" | Story 2 | `[manual]` | ✓ |
| 3 | FR4 Filament API grounding (AD3) | "Ship a stable `references/nova-taxonomy.md` plus per-Filament-major mapping tables (e.g. `nova-to-filament-v3.md`, `-v4.md`)… Bundled tables make the common case fast and offline" | "`references/` contains per-Filament-major mapping tables (e.g. `nova-to-filament-v4.md`) mapping each Nova primitive from the taxonomy to its Filament construct and a disposition class (`direct` / `behaviour-change` / `[rebuild]`)" | Story 1 | `[manual]` | ✓ |
| 4 | FR4 Filament API grounding (AD3) | "Bundled tables make the common case fast and offline" | "Bundled mapping tables function offline — the common case needs no network" | Story 1 | `[manual]` | ✓ |
