# Changelog

All notable changes to `claude-skills` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/), and this project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- **`research`** skill — migrated in from a standalone research-notebook directory,
  made portable, and locked to user invocation. Runs research sessions into a **notebook**: a directory of
  dated `YYYY-MM-DD-subject-slug` sessions plus a generated `INDEX.md`, with four
  modes (new session, continue, enquire read-only across the corpus, list). Ships
  the three session templates (`README`/`findings`/`sources`) and a read-only
  `scripts/index-rows.sh` that emits the index rows.
  - **New Step 0 — resolve the notebook root**, the substantive change the move
    required: the skill previously *was* the notebook repo and could assume the
    working directory. It now resolves explicit path → `$RESEARCH_NOTEBOOK` → the
    cwd if it looks like a notebook → a `research/` / `docs/research/` /
    `.research/` folder in the current project → ask, states which root it picked,
    and offers to make the choice durable. **Never invents a root**, so a dated
    session directory cannot be scaffolded into an application repo that merely
    happened to be the working directory.
  - Paths throughout are relative to the resolved root rather than the cwd, and the
    index script is invoked from the installed skill directory with the root passed
    as an argument (it already accepted one).
  - `scripts/index-rows.sh` — loop rewritten over a `nullglob` array instead of
    parsing `ls`, with an explicit not-a-directory error and exit 1; output verified
    byte-identical to the original against a real two-session notebook, plus the
    empty-notebook and bad-path cases.
  - **User-invoked only** — `disable-model-invocation: true` plus a Step 0 subagent
    refusal, so it takes a typed `/research` and prose like "look into X" no longer
    starts a session. The guard is deliberately narrow: it blocks a subagent from
    *owning* a session (choosing the mode, creating the directory, writing the session
    files or the index), because those are the points where the user has to be asked
    about an overlapping subject or an unresolvable root. Dispatching `Explore` agents
    for breadth *inside* an invoked session stays encouraged.
  - De-personalised (the source named its author throughout), and the "don't touch
    git" rule now also notes a notebook need not be a repository at all.
- **`explain-skill`** skill — explains one or more named skills or plugins as a
  single **one-page artifact**: invocation badges (who may call it, where it was
  found on disk), a when-to-use/when-not routing note, an options table built only
  from forms the source file documents, an inline-SVG behaviour flow with the
  **gates drawn distinctly** from automatic steps, and a worked example pair. Where
  the explained skill's own output is an artifact, that output is **embedded as a
  captioned facsimile** — scoped markup in a framed container, never a nested
  document or an `iframe` the CSP would blank. Resolution is by disk lookup with
  provenance recorded (project → personal → plugin cache → marketplace checkout);
  a harness-supplied skill with no file is labelled as described-from-listing with
  the unsubstantiated sections dropped. Accepts several targets; the header gains a
  sticky anchor **navigation bar only at two or more**. User-invoked only
  (`disable-model-invocation: true` plus a Step 0 subagent refusal), on the same
  reasoning as `show-me`: the page publishes under the user's account and the
  closing report is a conversation with them.
- `README.md` **Included skills** row for `explain-skill`.

### Changed

- **`show-me`** — locked to user invocation. The skill sets
  `disable-model-invocation: true`, so the harness admits only a typed
  `/show-me`, and a **Step 0** refuses the run outright when it is reached from a
  subagent (including via a dispatched one). The reason is structural rather than
  stylistic: the skill's second half puts a decision to the user with
  `AskUserQuestion`, which a subagent has no channel for, and "the last thing
  posed" is only visible in the session where it was posed. The natural-language
  triggers came out of the description accordingly — prose like "show me that" no
  longer starts it.

## [1.1.0] — 2026-08-13

Six new skills, plus **output styles** as a second distributable asset class with
its own install path.

### Added

- **`nova-to-filament`** skill — audits a Laravel Nova app and all its customisation
  against a fixed taxonomy of Nova primitives, then emits a cpm-compliant migration
  spec under `docs/specifications/` (feeding `/cpm:epics`) mapping every primitive
  one-to-one onto a specified FilamentPHP version. Built from a reconciliation ledger
  with a hard gate; anything unmappable is flagged `needs-human` / `[rebuild]` rather
  than given a faked mapping. Plans the migration — it does not perform it.
- **`stakeholder-rewrite`** skill — rewrites a technical document into plain language
  for a non-technical audience (board, trustees, funders, service managers, the
  public) using ASD-STE100 Simplified Technical English as the discipline. Applies
  the STE rules that remove ambiguity, relaxes the maintenance-manual register a
  governance paper does not need, preserves every figure, date, and caveat exactly,
  and labels output "plain-language, STE-informed" unless genuine compliance was
  checked. Ships a non-redistributable-document guard for the ASD standard itself.
- **`operator-rewrite`** skill — the operational sibling of `stakeholder-rewrite`,
  for the people who *run* the thing: user guide, quick reference, runbook/SOP, or
  "what changes for you" note. Where the sibling strips detail, this one **protects**
  it — extracting an operational spine first and gating on "did every rule survive?"
  — and applies STE's procedural machinery in full. Reuses the sibling's ASD-STE100
  references by path rather than duplicating them.
- **Output styles as a second distributable asset class.** The repo now ships
  `.claude/output-styles/*.md` alongside `.claude/skills/`, with its own install
  path — Claude Code reads output styles only from `~/.claude/output-styles/` (or a
  project `.claude/output-styles/`) and `claude --add-dir` does **not** register
  them, so `./install.sh` is required for styles regardless of how skills were
  installed. Symlinked styles were verified to be honoured, so the installer
  symlinks rather than copies, keeping `git pull` as the whole update story.
  - **`Terse`** output style — answer-first, minimal prose: leads with the result,
    one line per file touched, no preamble or recap. Explicitly exempts code,
    commits, generated documents, and plan-mode plans from the brevity rule, and
    holds investigation and verification at full depth; fuller reasoning is
    available on request.
  - **`terse`** skill — toggles that style on or off by editing only the
    `outputStyle` key in the first settings file that already defines it
    (project `settings.local.json` → project `settings.json` → global), treating
    the built-in `default` as "off".
  - `install.sh` — second symlink loop over `.claude/output-styles/*.md` into
    `~/.claude/output-styles/`, with its own count line; skipped cleanly when the
    directory is absent.
  - `README.md` — **Included output styles** table, an **Output styles** install
    section, an **Adding an output style** contributor section, and a caveat on the
    `--add-dir` route noting it does not cover styles.
  - `CLAUDE.md` — sync rule extended to cover the output-styles table and the
    output-styles conventions.
- **`code-to-uml`** skill — maps how a subject (a comment, an order, a webhook, a
  job) moves through one or more repositories from the real code, then builds a
  **self-contained HTML artifact** of hand-authored SVG **UML activity and sequence
  diagrams** (plus optional system-context and data-model panels). Accuracy-first
  (every node/lifeline/message traces to verified `file:line`) and CSP-safe
  and hand-authored (inline SVG only — Mermaid renders natively in artifacts but is
  ruled out on layout control, and the CDN-based renderers are blocked by the CSP).
  Ships a `references/svg-uml-kit.md` drawing kit and an `assets/artifact-scaffold.html`
  theme-aware starter; defers to the `artifact-design` skill for visual calibration.
- **`show-me`** skill — explains the most recently posed question, position, or
  statement as a self-contained visual artifact: a fixed subject-resolution order
  (an unanswered gate → a stated position → pasted content → the user's own
  question), the confusion type mapped to a diagram form, and hand-authored inline
  SVG throughout. Its signature discipline is the **carry-back**: every fork the
  artifact names is re-presented in the session via `AskUserQuestion` with matching
  labels and meaning — no silently dropped options, and no manufactured decision
  where the artifact presents none. Grounds repo-related claims in real code and
  marks inferred nodes.
- `README.md` **Included skills** rows for `code-to-uml` and `show-me`.

### Changed

- **`code-to-uml`** — corrected the stated reason Mermaid is excluded. The Artifact
  runtime *does* render Mermaid natively, so the previous "the CSP blocks it, it
  renders nothing" claim was wrong; the exclusion now rests on layout control
  (auto-layout cannot express repo-coloured swimlanes, `inferred` nodes, exact
  fragment placement, or token-driven theming). The CSP claim still stands for
  PlantUML, kroki, and other CDN-based renderers.

## [1.0.0] — 2026-07-02

First tagged release: the shared Claude Code skill set, distributed by symlink
(`./install.sh`) or `claude --add-dir`, with runtime guardrails in `settings.json`.

### Skills shipped

- **`brief-to-mockups`** — turns a product brief (with functional requirements)
  into a single-file HTML showcase + locked style guide; routes surfaces to the
  bespoke or Filament lane at intake.
- **`mockup-to-blade`** — turns a mockup into production **bespoke** Blade/Livewire
  UI (fidelity = shared markup, verbatim classes).
- **`mockup-to-filament`** — turns a mockup into production **Filament** admin/CRUD
  UI (fidelity = shared theme, not markup; `fi-*` never hand-extracted).
- **`de-ai-copy`** — audits customer-facing text/graphics and strips
  obviously-AI-generated vocabulary and presentation.
- **`laravel-dtos`** — replaces data-clump argument lists with DTOs / value objects.
- **`solid-spec`** — surveys PHP/Laravel/JS for SOLID violations and writes a
  cpm-compliant refactor specification.
- **`markdown`** — HTML-safe markdown with correct hard line breaks; ships an
  optional `PostToolUse` reformatting hook.

### Added

- **Mockup skills substrate split**:
  - Split the former `mockup-fidelity` skill into destination-named build lanes —
    `mockup-to-blade` (bespoke) and the new `mockup-to-filament` (Filament) — each
    with a hard Step-0 refuse-to-run guard (asymmetric on a missing routing table:
    the bespoke lane asks/stops, the Filament lane proceeds).
  - Promoted the per-surface routing table to a **durable artifact** at
    `docs/mockups/surface-routing.md` (schema `surface · FRs · substrate · producer
    · builder`), written by `brief-to-mockups` and read by both builders' Step-0
    guards as their first act.
  - Added an identically-worded produce/consume **convention line** + sibling
    cross-refs to every family skill's description; scoped the "verbatim classes /
    never re-skin" rule to bespoke rows only in the managed `CLAUDE.md` block.
- **Check scripts** under `scripts/` covering the split's structural acceptance
  criteria (rename integrity, new-skill presence, convention line, README
  folder↔row correspondence, and a repo-wide dangling-reference sweep).
- **`CHANGELOG.md`** and a version marker in `README.md`.
- **`.gitignore`** rule for transient CPM session state (`docs/plans/.cpm-*`).

### Changed

- `README.md` **Included skills** table synced to the current skill set
  (`mockup-fidelity` row replaced by `mockup-to-blade`; `mockup-to-filament` added).

### Removed

- The `mockup-fidelity` skill (renamed to `mockup-to-blade`); no dangling by-name
  references remain on the live skill surface.

### Notes

- The cross-repo counterpart (the published `filament-mockup` skill in the
  `claude-code-marketplace` repo, spec requirements M8/M9) is delivered separately
  via that repo's own PR + plugin reinstall — it is not part of this repo's release.
