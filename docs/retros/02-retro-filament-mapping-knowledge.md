# Retro: Filament Mapping Knowledge

**Date**: 2026-07-20  
**Source**: docs/epics/01-02-epic-filament-mapping-knowledge.md  
**Stories**: 2/2 complete

## Summary

Built the skill's mapping knowledge base — the bundled Nova→Filament tables and the runtime grounding
rules. Both stories delivered cleanly, and a mid-epic correction (v5 is the current Filament major, not
v4) was absorbed without rework because the grounding design already treats bundled tables as hypotheses.
The three-tier grounding precedence is a reusable pattern worth carrying forward.

## Observations

### Smooth Deliveries

- Authoring the mapping table *category-by-category against the taxonomy* made the "every category has a  
  row" cross-check trivial — 1:1 by construction, the same completeness-by-construction discipline as  
  Epic 01-01. The v4/v5 structural notes (fields split into columns + components, unified Actions  
  namespace, Schemas) pre-empt the classic "one Nova field = one Filament thing" mismapping.

### Patterns Worth Reusing

- **Three-tier grounding precedence with `needs-human` as the floor.** Installed `vendor/filament`  
  source > live docs > bundled table: the bundled table keeps the common case fast and offline, while the  
  higher tiers prevent fabricated mappings and `needs-human` catches what none can resolve. This is the  
  reusable spine for any skill that maps a known API onto a versioned target. It also made the v5  
  correction cheap — the table is a starting hypothesis, corrected at run time, so a "wrong current  
  major" was a content swap, not a design change.

## Recommendations

- When shipping any offline knowledge base that must track a live, versioned authority, adopt the  
  three-tier precedence + `needs-human` floor rather than baking assumptions into the bundled copy.
- Re-confirm the bundled v5/v4 tables at first real use (they are grounded from docs, not yet exercised  
  against an installed `vendor/filament`); the retrospective refinement pass is the natural point to do so.
- The three-tier grounding precedence is a strong library-promotion candidate once a second skill needs it.
