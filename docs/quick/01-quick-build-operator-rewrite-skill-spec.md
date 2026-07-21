# Build the `operator-rewrite` skill

**Date**: 2026-07-21  
**Status**: Complete

## Context

`stakeholder-rewrite` strips implementation detail for remote decision-makers; pointed
at operational readers it removes exactly what they need. This skill is the other half of
the pair — built from `~/.claude/skills/users-rewrite/SPEC.md` and shipped under the name
`operator-rewrite` (chosen from the SPEC's open question §10 by the routing axis: the
reader operates). Done via quick execution because it closely mirrors the
`stakeholder-rewrite` build completed earlier this session.

## Acceptance Criteria

- `operator-rewrite/SKILL.md` exists; `name: operator-rewrite` + operational triggers and  
  "operate/follow/act repeatedly → this skill" routing rule — **Met**
- `references/ste-application.md` states the inversion (procedural STE in full, dictionary  
  matters more, "STE-informed") — **Met**
- SKILL.md defines Step-3 operational-spine extraction and Step-5 "did every rule survive?"  
  coverage gate — **Met**
- Both skills name each other with the routing rule — **Met** (reciprocal links verified)
- No ASD-STE100 text/dictionary reproduced verbatim; shared refs cited by relative path,  
  not duplicated — **Met** (only `ste-application.md` + `skeletons.md` in the folder)
- README lists `operator-rewrite`; folder/row parity holds — **Met** (11 folders / 11 rows)

## Changes Made

- `.claude/skills/operator-rewrite/SKILL.md` — new skill: the pair/routing table, STE-fit  
  note, six-step procedure (locate → establish operator+task → extract spine → task-first  
  rewrite → coverage gate → output format), what-not-to-do.
- `.claude/skills/operator-rewrite/references/ste-application.md` — new; inverted STE note.
- `.claude/skills/operator-rewrite/references/skeletons.md` — new; four output skeletons.
- `.claude/skills/stakeholder-rewrite/SKILL.md` — added reciprocal "The pair, and how to  
  pick" routing pointer to `operator-rewrite`.
- `README.md` — added `operator-rewrite` row beside its sibling.

## Verification

Self-assessment by inspection (no test runner applies to markdown skill authoring):
grep-confirmed frontmatter name, routing rule, spine/coverage-gate sections, and reciprocal
cross-references; confirmed the reused ASD-STE100 PDF and `ste-rules.md` resolve via the
`../stakeholder-rewrite/references/` relative path and are not copied into this skill;
confirmed 11 skill folders map one-to-one to 11 README rows. Claude Code discovered the
new skill mid-session (description surfaced), confirming the frontmatter parses.

## Retro

**Pattern worth reusing**: Building a paired/complementary skill goes fastest by mirroring
the sibling's file layout and inverting its controlled-language note section-by-section,
reusing shared references by relative path rather than duplicating the licensed source.
