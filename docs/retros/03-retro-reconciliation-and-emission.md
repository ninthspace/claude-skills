# Retro: Reconciliation & Emission

**Date**: 2026-07-20  
**Source**: docs/epics/01-03-epic-reconciliation-and-emission.md  
**Stories**: 4/4 complete

## Summary

The heart of the skill — the reconciliation ledger with its hard pre-write gate, cpm-compliant spec
emission, the coverage manifest, and the migration sequencing hint. All four stories delivered cleanly.
Two reusable patterns emerged: reconcile-to-census as an arithmetic completeness proof, and extending a
fixed output contract by appending rather than reordering.

## Observations

### Smooth Deliveries

- The coverage manifest and the sequencing hint were both *views over data the audit already produced* —  
  file list, census, disposition tally, could-not-classify section for the manifest; the taxonomy §9  
  relationship edges for the topological sequence. No new machinery, just honest projections. When the  
  earlier phases capture the right data, later deliverables become near-free.
- The one genuinely non-obvious case — circular `BelongsTo` references in the sequencing graph — was  
  handled explicitly (record the cycle, pick a pragmatic entry point) rather than assumed away, keeping  
  the hint honest for real Nova apps.

### Patterns Worth Reusing

- **Reconcile-to-census as an arithmetic gate.** Making the ledger balance against the census (row count  
  == census count per category) turns "did we drop anything?" from a judgement call into arithmetic. The  
  same invariant runs from the fan-out audit through the ledger to the coverage manifest — one rule  
  enforced at every stage is what makes the end-to-end completeness claim provable.
- **Extend a fixed contract by appending, not reordering.** Shipping a bundled `references/spec-template.md`  
  (mirroring `solid-spec`) and appending the Disposition column *after* the three canonical cpm columns  
  let the reconciliation ledger and the Acceptance Criteria Coverage table be one artifact without  
  forking the downstream contract — the `cpm:epics` parser still finds Requirement/Criterion/Tag in  
  place. Reuse whenever a skill must extend a fixed output contract: add trailing, never reorder.

## Recommendations

- Both patterns worth reusing (reconcile-to-census; append-don't-reorder) are library-promotion  
  candidates — they generalise well beyond this skill.
- When a downstream contract is fixed, always prefer a bundled snapshot + trailing extension over a  
  bespoke format; verify the extension is inert to the consumer's parser (as done here with the trailing  
  Disposition column).
- The hard pre-write gate is unexercised against a real emitted spec — validate the `AskUserQuestion`  
  routing of `needs-human` rows during the first full lifecycle run.
