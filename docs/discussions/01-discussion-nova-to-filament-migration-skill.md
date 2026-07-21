# Discussion: Nova → Filament migration-spec generator skill

**Date**: 2026-07-20  
**Agents**: Margot (Architect), Bella (Senior Dev), Tomas (QA), Casey (Test), Sable (DevOps), Elli (Writer), Jordan (PM)

## Discussion Highlights

### The brief
Design a new Claude skill (to live in this `claude-skills` repo) that analyses an existing Laravel Nova application — including all customisation (custom fields, actions, etc.) — and emits a `/cpm:epics`-ready spec that maps every piece of Nova functionality one-to-one onto a specified FilamentPHP version. Bar set by Chris: **completeness and accuracy**, because a gap in the spec becomes a silent gap in the migration.

### Key points

- **Fixed Nova taxonomy** is the completeness spine AND the audit checklist (Margot): Resources, every Field type, Actions, Filters, Lenses, all 4 Metric types (value/trend/partition/progress), Dashboards, Cards, custom Tools, policies/authorization, relationships. Each category is walked and reports a count (even zero) so nothing is forgotten by construction — you can't forget to look for Lenses if Lenses are a mandatory checklist entry.
- **Three/four-bucket mapping, no false equivalence** (Bella): `direct` (Text → TextInput) / `behaviour-change` (Nova metrics → Filament widgets, different API) / `[rebuild]` (custom Vue fields & Tools — Filament is Livewire/Alpine, so these are rebuilds, not maps) / `needs-human` (unclassifiable closure logic). Flattening these into one "mapping" column would lie.
- **Audit reads implementation, not declarations** (Bella): read the *bodies* of `fields()`, `Action::handle()`, filters, and policies plus their callbacks (`resolveUsing`/`displayUsing`/`dependsOn`/`canSee`/custom validation/computed accessors). Grepping `Text::make('name')` finds the field but misses the behaviour that must survive the migration.
- **Reconciliation ledger + hard gate** (Tomas, extending Chris's "audit existing implementation → ensure a Filament equivalent"): a two-column ledger where no left-hand (audited Nova item) row may be empty on the right (Filament disposition). Every item resolves to exactly one of the four dispositions. The skill refuses to finalise the spec while any row is unresolved, and prints disposition counts (e.g. "142 direct, 9 behaviour-change, 4 rebuild, 2 need decisions"). A `needs-human` cell is honest, not a failure; a silent omission is the real failure.
- **Version stamping** (Sable): resolve the Nova version from the target's `composer.lock` (Nova 4 vs 5 differ); pin the target Filament version in the spec header. Read `vendor/filament` source as ground truth when Filament is installed, live docs otherwise. A migration spec with no version stamps is unauditable.
- **Behavioural parity, not pixel parity** (Casey): each FR carries parity acceptance criteria at the right test level — standard field → assert form/table columns + validation; Action → feature test that the same records are affected; `[rebuild]` custom field → honest manual-verification criteria (no automatic equivalence). Resisting pixel parity avoids over-testing; behavioural parity keeps "one-to-one" checkable.
- **Output = cpm-compliant spec, like `solid-spec`** (Elli): emit to `docs/specifications/{nn}-spec-{slug}.md` using the fixed cpm spec template (MoSCoW grouping, test-tag vocabulary, Acceptance Criteria Coverage table). One FR per Nova primitive; the mapping is expressed *as* the acceptance criteria; the reconciliation ledger doubles as the coverage table. Add an explicit `[rebuild]` tag to the tag vocabulary so bespoke items are greppable. Don't invent a new format.

### Decisions locked by Chris

1. **Gap handling** — unmappable items are flagged as **bespoke rebuilds** (honest "no direct equivalent — requires custom Filament build" + a rebuild story). Never fake a mapping.
2. **Filament API knowledge** — use **live official docs for the pinned version _plus_ inspect the target's installed Filament source**; read `vendor` code directly when documentation is missing or unclear.
3. **Manifest approach** — **audit the existing implementation, then ensure a Filament equivalent for each item** (the reconciliation loop).

### Consensus recommendation (the design)

Build the skill as a **taxonomy-driven audit → reconciliation ledger → cpm-spec emitter**:

1. Fixed Nova taxonomy as both audit checklist and spec spine; every category walked and counted (Margot).
2. Audit reads implementation bodies and callbacks, not just declarations (Bella).
3. Version stamping: Nova from `composer.lock`; Filament pinned + `vendor/filament` source as ground truth when installed, else live docs (Sable + Chris's decision 2).
4. Reconciliation ledger with a hard gate: every item resolves to `direct` / `behaviour-change` / `[rebuild]` / `needs-human`; won't finalise with unresolved rows; reports counts (Tomas + Chris's decision 3).
5. Output a cpm-compliant spec (à la `solid-spec`): one FR per primitive, mapping expressed as acceptance criteria at the right test level (behavioural parity, not pixel parity); ledger *is* the coverage table; `[rebuild]` items get manual-verification criteria (Elli + Casey).

### Sibling-skill grounding

- `.claude/skills/solid-spec/` is the closest precedent: analyses code → writes a cpm-compliant spec at `docs/specifications/{nn}-spec-{slug}.md` using a fixed `references/spec-template.md`. The new skill should follow the **same output contract**, not invent one.
- Per this repo's `CLAUDE.md`: any new skill must be one folder under `.claude/skills/<name>/` with an uppercase `SKILL.md`, and the **"Included skills" table in `README.md` must be updated in the same change**.

### Threads not yet explored (open for a later session)

- **Priya (UX)**: how custom Nova Tools' user journeys translate to Filament — the rebuild bucket is where UX drift is most likely.
- **Jordan (PM)**: scope boundaries — is the skill's job only the spec, or also prioritisation of which resources migrate first?
- **Ren (SM)**: epic sizing — a large Nova app could produce a very large spec; how does it break into consumable epics?
