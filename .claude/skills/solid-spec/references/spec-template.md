# cpm:spec output template

Emit the specification with **exactly** these section headings, in this order. Downstream `cpm:epics` keys off the headings, the MoSCoW labels, the test tag table columns, and the `R{N}` requirement labelling — paraphrasing breaks the contract.

The bracketed `{...}` items are placeholders to fill in. Anything in plain prose is the cpm contract — keep it verbatim unless this template explicitly marks it variable.

```markdown
# Spec: {Title — short, descriptive, e.g. "Note model debt remediation"}

**Date**: {YYYY-MM-DD — today's date}
**Brief**: {if produced from another doc: a markdown link to it; otherwise omit this line}

## Problem Summary

{One paragraph. State which area of the codebase the SOLID violations cluster in, and why now (e.g. "audit X surfaced these", "user is iterating on Y and the structure is fighting them"). Mention how many findings cluster here and what categories they fall under at a high level.}

## Functional Requirements

### Must Have

- **R1 ({short slug — `{principle}-{file/symbol}` format, e.g. "SRP — extract NoteFileCleaner"})**: {One sentence stating the requirement in MUST language. Cite the affected files inline with `file:line (symbol)`. Spell out the post-condition: what the code looks like after the change. The R label must be unique; downstream skills reference R-labels by number.}
- **R2 ({short slug})**: {…}
- {…}

### Should Have

- {Bullet items in plain prose — same format as Must-Have but using SHOULD language. No R-label required for shoulds; they're not pulled into stories unless promoted.}

### Could Have

- {Tidy-ups discovered during the sweep that aren't worth their own story but are worth recording. Same prose format.}

### Won't Have (this iteration)

- {Findings the sweep surfaced but the user explicitly deferred or rejected. Always include this section even if empty — listing nothing here makes "we considered it" invisible.}

## Non-Functional Requirements

{Only include subsections that are actually relevant to a SOLID refactor. For most refactors:}

- **Reliability**: {test suite must pass after each merge; static analysis (PHPStan/Psalm/TS) must remain green on touched files; specify the commands.}
- **Maintainability**: {expected line-count change on the touched files; explicit non-goals like "no behaviour change" if applicable.}
- **Backwards compatibility**: {whether public API of the touched classes is preserved; which methods retain their signatures; which become private/move; whether routes/URLs change.}

{Skip Performance / Security / Scalability / Usability subsections unless the refactor genuinely involves them.}

## Architecture Decisions

{One AD-N block per significant structural choice. Aim for 3-7 ADs on a typical SOLID spec. ADs map to a Must-Have requirement and explain *the chosen shape*.}

### AD-1: {Decision title — one short noun phrase, e.g. "Extract NoteFileCleaner as injected service"}

**Choice**: {Concrete description: namespace, class name, method shape, where it's wired. Specific enough that a developer could open the right file and start typing.}
**Rationale**: {Why this shape over alternatives. Reference existing project conventions discovered during the sweep, e.g. "matches App\Services\{Domain}\ pattern used in App\Services\TaskSync\ and App\Services\Plan\". One short paragraph.}
**Alternatives considered**: {At least one other shape and why it was rejected. E.g. "Trait on Note (rejected — preserves the model coupling). Action class per cleanup operation (rejected — three operations share helpers; one service is more cohesive)."}

### AD-2: {…}

## Scope

### In Scope

- {Bullet items naming the requirements (by R-label) and any side-effect changes the refactor inevitably touches.}

### Out of Scope

- {Findings explicitly excluded. Reference R-labels demoted from Must-Have or items demoted to "Won't Have".}

### Deferred

- {Things to handle after this cycle but worth flagging now. Often: arch tests to add, follow-on refactors that depend on this one landing first.}

## Testing Strategy

### Tag Vocabulary

Test approach tags used in this spec:

- `[unit]` — Unit tests targeting individual components in isolation
- `[integration]` — Integration tests exercising boundaries between components
- `[feature]` — Feature/end-to-end tests exercising complete user-facing workflows
- `[manual]` — Manual inspection, observation, or user confirmation
- `[tdd]` — Workflow mode: red-green-refactor loop. Composable with any level tag (e.g. `[tdd] [unit]`)

### Acceptance Criteria Coverage

| Requirement | Acceptance Criterion | Test Approach |
|---|---|---|
| R1 ({short slug}) | {Specific, observable criterion drawn from the finding's recommendation. E.g. "After R1, `App\Services\Notes\NoteFileCleaner` exists with the three documented public methods and `NoteObserver` consumes it via constructor injection."} | `[unit]` |
| R1 ({short slug}) | {Second criterion if the requirement has multiple verifiable post-conditions. Many requirements need 2-3 rows.} | `[integration]` |
| R2 ({short slug}) | {…} | `[unit]` |

{Every Must-Have requirement has at least one row. Use `[manual]` only if automation is genuinely infeasible — for SOLID work that's rare. Most SOLID criteria are `[unit]` (the new collaborator exists with the right shape) plus `[integration]` (the consumer wires it correctly).}

{For must-NOT clauses (e.g. "The model file MUST NOT contain the file-cleanup helpers after R3"), add their own row. Arch tests are a strong fit for `must NOT` enforcement.}

### Integration Boundaries

{Optional. List the new seams the refactor introduces — the public contract between extracted collaborators and their consumers. Each boundary becomes a candidate for an integration test.}

- {ConsumerA → ExtractedB: contract is {description}}
- {ExtractedB → ExternalC: contract is {description}}

### Test Infrastructure

{What testing infrastructure the refactor needs that doesn't already exist. For SOLID refactors this is usually "None required" — existing unit/integration tests cover it. Flag if the refactor needs a Pest browser test for JS-in-template extraction (often the case).}

{Items listed here become stories in cpm:epics.}

### Unit Testing

Unit testing of individual components is handled at the `cpm:do` task level — each story's acceptance criteria drive test coverage during implementation.
```

## Filling notes (not part of the template)

- **Title** should describe the *outcome*, not the activity. "Note model debt remediation" beats "SOLID refactor of Note.php".
- **R-label slugs** travel with the requirement through epics and tasks. Pick something that's still readable two weeks later: `R3 (extract NoteFileCleaner)` is better than `R3 (refactor model)`.
- **Citations** belong inside the requirement text, not in a separate "see also" list. The point of cpm:spec is that one document is enough context for a story-writer; pulling out citations into a footnotes section makes the reader page-flip.
- **Acceptance criteria** are the contract. Write them as observable post-conditions ("X file exists with Y method" / "Z controller consumes W via constructor injection"), not as activities ("refactor X"). The test verifies the post-condition.
- **Don't pad ADs.** If a Must-Have is purely mechanical (e.g. R7 narrowing `$fillable`), it doesn't need an AD — the requirement text already describes the choice. ADs are for *non-trivial* shape decisions.
- **No "Architecture Decisions" section is fine** if every Must-Have is mechanical. Omit the section rather than padding.
