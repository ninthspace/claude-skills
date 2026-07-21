# Nova → Filament migration spec — output template

Emit the migration specification with **exactly** these section headings, in this order. This is the
cpm spec contract (the same one `cpm:spec` and `solid-spec` emit): downstream `cpm:epics` keys off the
headings, the MoSCoW labels, the `R{N}` requirement labelling, and the Acceptance Criteria Coverage
table columns — paraphrasing breaks the contract. **Do not fork it.** The only additions this skill
makes to the base contract are (a) the version-stamped header lines, (b) the `[rebuild]` tag in the tag
vocabulary, and (c) a trailing **Disposition** column on the coverage table so the reconciliation ledger
*is* that table. The first three coverage columns are the canonical contract, unchanged.

Bracketed `{...}` items are placeholders. Plain prose is the contract — keep it verbatim unless marked
variable.

```markdown
# Spec: {Title — outcome-focused, e.g. "Migrate the Admin panel from Nova to Filament v5"}

**Date**: {YYYY-MM-DD — today's date}
**Nova version**: {resolved installed Nova version, e.g. 4.35.2 — or "unknown (constraint: {c})"}
**Target Filament version**: {the pinned constraint, e.g. ^5.0}
**Laravel / PHP**: {resolved framework + PHP constraint, for install feasibility context}
**Audit tooling**: {which authoritative tooling was used, e.g. "Laravel Boost application-info; composer.lock" — or "static Glob/Grep/Read only"}

## Problem Summary

{One paragraph. State that this spec plans a one-to-one migration of a Laravel Nova admin to the pinned
Filament version, how many Nova primitives were audited in total, and the headline disposition tally
(e.g. "157 items: 142 direct, 9 behaviour-change, 4 rebuild, 2 needs-human"). Name the highest-risk
areas (custom Tools, custom fields) up front.}

## Functional Requirements

### Must Have

- **R1 ({slug — `{primitive}-{identity}`, e.g. "resource-User"})**: {One sentence in MUST language:
  the Nova primitive MUST be reproduced in Filament as {target construct}, preserving {the captured
  behaviour}. Cite the Nova source inline with `file:line (symbol)`. One R-label per Nova primitive
  (or tightly-related group); the R label is unique and travels downstream.}
- **R2 ({slug})**: {…}
- {… one Must-Have requirement per audited Nova primitive that must survive the migration …}

### Should Have

- {Behaviour-change items whose parity is desirable but non-blocking, in SHOULD language. Includes the
  migration sequencing preference if not elevated to Must.}

### Could Have

- {Nice-to-haves surfaced during the audit — UI polish Filament enables, consolidations.}

### Won't Have (this iteration)

- {Explicitly deferred items — e.g. a `[rebuild]` custom Tool the user postpones. Always include this
  section even if empty, so "we considered it" is visible.}

## Non-Functional Requirements

{Only relevant subsections. For a Nova→Filament migration, typically:}

- **Behavioural parity**: {the migrated panel MUST preserve authorization rules, validation, and data
  behaviour; parity is behavioural, not pixel-level (see Testing Strategy).}
- **Reliability**: {existing tests must pass; specify the command. Data must not be mutated by the
  migration itself.}
- **Backwards compatibility**: {routes/URLs, stored data formats (e.g. Trix vs RichEditor HTML), and
  disk/storage config that must remain compatible.}

## Architecture Decisions

{One AD-N block per significant migration-shape choice — e.g. how custom Tools are rebuilt, how Nova
lenses map to Filament pages, panel/provider structure. Aim for 3-7.}

### AD-1: {Decision title — one short noun phrase}

**Choice**: {Concrete: the Filament construct, namespace, where it is wired.}
**Rationale**: {Why this shape, referencing the grounding source (docs / installed vendor version).}
**Alternatives considered**: {At least one rejected shape and why.}

### AD-2: {…}

## Scope

### In Scope

- {Requirements by R-label; the panels/resources covered.}

### Out of Scope

- {Deferred or rejected items by R-label.}

### Deferred

- {Post-migration follow-ups worth flagging now.}

### Migration Sequencing

{The proposed migration order (FR9). List resources/primitives in dependency order — those with no
outbound relationships first, then dependents — so downstream `cpm:epics` inherits a sensible sequence.
State the ordering rule used (e.g. "topological by BelongsTo/relationship edges; standalone resources
first").}

## Testing Strategy

### Tag Vocabulary

Test approach tags used in this spec:

- `[unit]` — Unit tests targeting individual components in isolation
- `[integration]` — Integration tests exercising boundaries between components
- `[feature]` — Feature/end-to-end tests exercising complete user-facing workflows
- `[manual]` — Manual inspection, observation, or user confirmation
- `[tdd]` — Workflow mode: red-green-refactor loop. Composable with any level tag (e.g. `[tdd] [unit]`)

Disposition tags (this skill's addition — the reconciliation disposition per Nova primitive):

- `[rebuild]` — No direct Filament equivalent; the functionality is rebuilt (Nova custom Vue → Filament
  Livewire/Blade, or a feature Filament core lacks). Marks the highest-risk requirements.

### Acceptance Criteria Coverage

This table **is** the reconciliation ledger: one row per audited Nova primitive, every row carrying a
single disposition. The first three columns are the canonical cpm contract; **Disposition** is appended.

| Requirement | Acceptance Criterion | Test Approach | Disposition |
|---|---|---|---|
| R1 ({slug}) | {Behavioural parity post-condition — observable, not pixel-level. E.g. "The Filament User resource exposes name/email fields with the same validation rules and the same policy-gated create/update/delete as `app/Nova/User.php`."} | `[feature]` | direct |
| R2 ({slug}) | {A `behaviour-change` row states the re-expressed behaviour, e.g. "The `resolveUsing` transform on `title` is reproduced via `->formatStateUsing()`."} | `[integration]` | behaviour-change |
| R3 ({slug}) | {A `[rebuild]` row scopes the rebuild, e.g. "The `RevenueTool` custom Tool is rebuilt as a Filament page reproducing its three endpoints and chart."} | `[feature]` | `[rebuild]` |
| R4 ({slug}) | {A `needs-human` row must be resolved before the spec is finalised — if any remain, the gate has not passed. It should not appear in a finalised spec except as an explicitly user-accepted open item.} | `[manual]` | needs-human |

{Every Must-Have requirement has at least one row, and the row count reconciles to the audit census
total. Parity criteria are **behavioural, not pixel-level** — assert on preserved rules/data/behaviour,
never on visual match. Use `[manual]` only where automation is genuinely infeasible (visual/UX
judgement).}

{For must-NOT clauses (e.g. "the migrated action MUST NOT run without the same authorization check"),
add their own row.}

### Integration Boundaries

{Optional. Seams the migration introduces — e.g. Filament panel ↔ existing policies, RelationManager ↔
pivot data. Each becomes a candidate integration test.}

### Test Infrastructure

{Testing infrastructure the migration needs that doesn't already exist. Items listed here become
stories in cpm:epics.}

### Unit Testing

Unit testing of individual components is handled at the `cpm:do` task level — each story's acceptance
criteria drive test coverage during implementation.
```

## Filling notes (not part of the template)

- **Title** describes the outcome ("Migrate the Admin panel to Filament v5"), not the activity.
- **One R-label per Nova primitive.** This is what makes the migration one-to-one: every audited item
  becomes a traceable requirement. R-slugs travel through epics and tasks — keep them readable
  (`R7 (action-PublishPost)`).
- **The coverage table reconciles to the census.** Its row count equals the audit census total; the
  disposition tally in the Problem Summary is the sum of the Disposition column. If they don't match,
  the pre-write gate has not passed — do not emit.
- **Citations** belong inside the requirement text (`file:line (symbol)`), not a separate list.
- **Parity is behavioural.** Acceptance criteria assert preserved behaviour (rules, data, authorization),
  never pixel/visual match.
- **No `needs-human` rows in a finalised spec** unless the user explicitly accepted them as documented
  open items — the reconciliation gate routes them through `AskUserQuestion` first.
