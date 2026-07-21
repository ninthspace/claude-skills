# Structural-Check Helper & Repo Integration

**Source spec**: docs/specifications/01-spec-nova-to-filament-migration-skill.md  
**Date**: 2026-07-20  
**Status**: Complete  
**Blocked by**: Epic 01-03-epic-reconciliation-and-emission

## Build the structural-check helper
**Story**: 1  
**Status**: Complete  
**Blocked by**: —  
**Satisfies**: FR5, FR6

**Acceptance Criteria**:

- A helper (bash/grep) validates that an emitted spec's reconciliation ledger has no empty disposition cells [integration]
- Validates that every taxonomy category is present in the emitted spec/manifest [integration]
- Validates that the emitted spec header carries the Nova + Filament version stamp [integration]
- Validates the emitted spec conforms to the cpm spec template structure (required headings, AC coverage table present) [integration]
- The helper is self-contained, reviewed as code, and exits non-zero on any violation [integration]

### Implement the structural-check helper script
**Task**: 1.1  
**Description**: Validates the emitted-spec invariants (no empty dispositions, all taxonomy categories present, version header stamped, cpm template conformance); exits non-zero on any violation. Reviewed as code per the repo convention for skills that ship executable scripts.  
**Status**: Complete

### Write tests for the structural-check helper
**Task**: 1.2  
**Description**: Auto-generated testing task — run the helper against a known-good and a known-bad sample emitted spec; assert a pass and a non-zero failure respectively. Covers the story's [integration] criteria.  
**Status**: Complete

**Retro**: [Pattern worth reusing] Shipping `check-spec.sh` + `test-check-spec.sh` + good/bad fixtures makes the skill's completeness invariants *executably enforceable* — the same "no empty dispositions / every category present / version-stamped" gate the skill runs pre-write can be re-verified over any emitted artifact in CI. A prompt-skill that emits a structured artifact benefits from a tiny structural linter over that artifact; reuse the fixture-pair (known-good exit 0, known-bad non-zero) as the test shape.

---

## Skill packaging & README integration
**Story**: 2  
**Status**: Complete  
**Blocked by**: Story 1  
**Satisfies**: —

**Acceptance Criteria**:

- The README "Included skills" table has a row for `nova-to-filament` derived from the skill's `description:` frontmatter [manual]
- `.claude/skills/nova-to-filament/` contains exactly one `SKILL.md` (uppercase) with its `references/` present, satisfying the one-folder-one-skill convention [manual]
- The skill is discoverable/invocable via the repo's install model (symlink / `--add-dir` per README) [manual]

### Add the README "Included skills" row
**Task**: 2.1  
**Description**: Repo `CLAUDE.md` convention — add a table row for `nova-to-filament`, deriving the one-line summary from the skill's `description:` frontmatter (condensed, not the full trigger list).  
**Status**: Complete

### Final SKILL.md assembly check
**Task**: 2.2  
**Description**: Uppercase `SKILL.md` filename, `references/` wired in, and a coherent end-to-end flow from invocation through audit, grounding, reconciliation, and emission.  
**Status**: Complete

**Retro**: [Smooth delivery] The final assembly check verified end-to-end coherence mechanically — one uppercase `SKILL.md`, all six phases present with no leftover stubs, `references/` cited (generically as `-v{major}` so the skill is version-agnostic), and the README table diffed clean against the skill folders. The repo's "keep the README skill list in sync" convention was satisfiable with a scriptable folders-vs-rows diff.

---
