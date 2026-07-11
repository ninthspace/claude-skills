# Changelog

All notable changes to `claude-skills` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/), and this project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- **`code-to-uml`** skill — maps how a subject (a comment, an order, a webhook, a
  job) moves through one or more repositories from the real code, then builds a
  **self-contained HTML artifact** of hand-authored SVG **UML activity and sequence
  diagrams** (plus optional system-context and data-model panels). Accuracy-first
  (every node/lifeline/message traces to verified `file:line`) and CSP-safe
  (inline SVG only — no Mermaid/PlantUML/CDN, which the Artifact runtime blocks).
  Ships a `references/svg-uml-kit.md` drawing kit and an `assets/artifact-scaffold.html`
  theme-aware starter; defers to the `artifact-design` skill for visual calibration.
- `README.md` **Included skills** row for `code-to-uml`.

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
