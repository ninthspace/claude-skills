# Retro: Structural-Check Helper & Repo Integration

**Date**: 2026-07-20  
**Source**: docs/epics/01-04-epic-helper-and-repo-integration.md  
**Stories**: 2/2 complete

## Summary

The final epic — a self-contained structural-check helper (with tests and fixtures) and the repo
integration (README row, packaging). Both stories delivered cleanly, and this epic carried the only
*executable* verification in the whole build: the helper's tests actually ran (good spec exits 0, bad
spec exits non-zero) rather than being self-assessed. Shipping a structural linter over the skill's own
output is the reusable idea.

## Observations

### Smooth Deliveries

- The final assembly check was mechanical and scriptable: one uppercase `SKILL.md`, all six phases  
  present with no leftover stubs, `references/` cited generically (`-v{major}`, keeping the skill  
  version-agnostic), and the README "Included skills" table diffed clean against the actual skill folders.  
  The repo's "keep the README list in sync" convention reduced to a folders-vs-rows `diff` — a convention  
  worth enforcing that way going forward.

### Patterns Worth Reusing

- **A tiny structural linter over a skill's structured output.** `check-spec.sh` + `test-check-spec.sh` +  
  good/bad fixtures make the skill's completeness invariants (no empty dispositions, every taxonomy  
  category present, version-stamped header, template conformance) *executably enforceable* — the same  
  gate the skill runs pre-write can be re-verified over any emitted artifact in CI. The fixture-pair  
  shape (known-good → exit 0, known-bad → non-zero) is a clean, dependency-free test pattern for any  
  prompt-skill that emits a structured artifact.

## Recommendations

- Adopt the "structural linter + fixture-pair" pattern for other CPM skills that emit structured  
  artifacts (specs, coverage matrices) — it converts prose invariants into a CI-runnable gate.
- Enforce the README-in-sync convention with the folders-vs-rows diff as a lightweight repo check.
- `check-spec.sh` ships as executable code and lands via reviewed PR per the repo convention — ensure the  
  script gets a code review at merge time (its awk table-parsing is the part most worth a second pair of  
  eyes).
