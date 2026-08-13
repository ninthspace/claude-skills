# claude-skills

A collection of shared [Claude Code](https://code.claude.com) skills.

**Version 1.1.0** — see [CHANGELOG.md](CHANGELOG.md) for what's in this release.

Skills live under `.claude/skills/`, one folder per skill. Output styles live under
`.claude/output-styles/`, one `.md` file per style — these install differently from
skills, see [Output styles](#output-styles). Runtime behaviour is constrained by the
shared `settings.json` deny-list.

## Included skills

| Skill | What it does |
| --- | --- |
| [`brief-to-mockups`](.claude/skills/brief-to-mockups/) | Turns a product brief (with functional requirements) into a set of mockups backed by a single, locked style guide. Produces a stack-agnostic single-file HTML showcase where every screen element traces back to an FR. Sits upstream of the build lane — `mockup-to-blade` (bespoke) and `mockup-to-filament` (Filament). |
| [`mockup-to-blade`](.claude/skills/mockup-to-blade/) | Turns an HTML mockup into a production **bespoke** Laravel UI (Blade/Livewire) that matches the mockup closely — decomposing a showcase into routes, classifying surface vs mechanism, extracting presentational Blade components with verbatim classes, locking design tokens/contracts/copy, and enforcing parity + coverage gates. The bespoke build lane; Filament surfaces go to `mockup-to-filament`. |
| [`mockup-to-filament`](.claude/skills/mockup-to-filament/) | Turns an HTML mockup into a production **Filament** admin/CRUD UI with fidelity to the design without hand-copying its markup — classifying screens to native Filament constructs, scaffolding resources/pages and a theme, and mounting bespoke Livewire in Filament chrome for custom surfaces. Fidelity is a shared *theme*, not shared markup (`fi-*` is themed, never hand-extracted). The Filament build lane; bespoke surfaces go to `mockup-to-blade`. |
| [`de-ai-copy`](.claude/skills/de-ai-copy/) | Audits customer-facing text and graphics in a repo (Laravel by default, any stack otherwise) and proposes conservative edits that strip the vocabulary and presentational habits that read as obviously AI-generated. |
| [`laravel-dtos`](.claude/skills/laravel-dtos/) | Reviews a Laravel/PHP codebase (or a scoped subset) and replaces long, repeated argument lists — data clumps, primitive obsession — with Data Transfer Objects / value objects that carry semantic meaning. |
| [`solid-spec`](.claude/skills/solid-spec/) | Surveys PHP, Laravel, and JavaScript (incl. JS in Blade/HTML) for SOLID violations, then writes a cpm-compliant refactor specification under `docs/specifications/` that downstream skills (epics, do, ralph) can pick up. |
| [`nova-to-filament`](.claude/skills/nova-to-filament/) | Audits a Laravel Nova app and all its customisation against a fixed taxonomy of Nova primitives, then emits a cpm-compliant migration spec under `docs/specifications/` (feeding `/cpm:epics`) that maps every primitive one-to-one onto a specified FilamentPHP version. Built from a reconciliation ledger with a hard gate; flags anything unmappable as `needs-human`/`[rebuild]` rather than faking a mapping. Plans the migration — it does not perform it. |
| [`markdown`](.claude/skills/markdown/) | Produces HTML-safe markdown with correct hard line breaks. Ships an optional `PostToolUse` hook that reformats `.md` files under `docs/` in place (opt-in via `settings.json`). |
| [`code-to-uml`](.claude/skills/code-to-uml/) | Maps how a subject moves through one or more repositories from the real code, then builds a self-contained HTML artifact of hand-authored SVG UML activity and sequence diagrams (plus optional system-context and data-model panels). No Mermaid (available, but its auto-layout can't hold the emphasis these diagrams depend on) and no CDN renderer (blocked by the Artifact CSP). |
| [`stakeholder-rewrite`](.claude/skills/stakeholder-rewrite/) | Rewrites a technical document into a plain-language version for a non-technical audience — board, trustees, funders, service managers, the public — using ASD-STE100 Simplified Technical English as the discipline. Applies the STE rules that remove ambiguity and relaxes the maintenance-manual register a governance paper doesn't need. Labels output "plain-language, STE-informed" unless genuine compliance is checked, and preserves every figure, date, and caveat exactly. |
| [`operator-rewrite`](.claude/skills/operator-rewrite/) | The operational sibling of `stakeholder-rewrite`: rewrites a technical document for the people who *run* the thing — front-line staff, process owners, new starters — as a user guide, quick reference, runbook/SOP, or "what changes for you" note. Where the sibling strips detail, this *protects* every rule, state, exception, and sequence, extracting an operational spine first and gating on "did every rule survive?". Applies STE's procedural machinery in full (its home turf). Reuses the sibling's ASD-STE100 references by path. |
| [`show-me`](.claude/skills/show-me/) | Explains the most recently posed question, position, or statement as a self-contained visual artifact — one-sentence answer, hand-authored SVG diagrams chosen for what's actually confusing (no Mermaid, matching `code-to-uml`), trade-offs presented fairly — then carries every fork the artifact names back into the session as an `AskUserQuestion` so understanding converts into a decision. Grounds anything repo-related in real code; refuses to manufacture a decision where the artifact presents none. |
| [`terse`](.claude/skills/terse/) | Toggles the **Terse** output style on or off by flipping the `outputStyle` key in your Claude Code settings ("off" is the built-in `default`). Ships the style itself at [`.claude/output-styles/terse.md`](.claude/output-styles/terse.md) — answer-first, minimal-prose replies with full detail on request; brevity governs the report, never the work. Requires the output-style install step (see [Output styles](#output-styles)). |

## Included output styles

| Output style | What it does |
| --- | --- |
| [`Terse`](.claude/output-styles/terse.md) | Answer-first, minimal prose. Leads with the result, one line per file touched, no preamble or recap — while explicitly exempting code, commits, documents, and plans from the brevity rule, and keeping investigation and verification at full depth. Toggled by the [`terse`](.claude/skills/terse/) skill. |

## How skills are discovered

Claude Code discovers skills exactly **one level deep**:
`<skills-dir>/<skill-name>/SKILL.md`. It does **not** recurse, so you cannot clone
this repo into `~/.claude/skills/` — the skills would end up nested too deep to be
found. Instead, clone it anywhere and point Claude at it with `--add-dir` (see below).

## Install (recommended: `--add-dir`)

`--add-dir` grants file access to a directory **and** auto-loads any
`.claude/skills/` it contains. This is the cleanest option — no copying, and
`git pull` is the entire update story.

```bash
# 1. Clone somewhere normal (NOT inside ~/.claude/)
git clone https://github.com/ninthspace/claude-skills.git ~/code/claude-skills

# 2. Make it automatic with a shell alias (add to ~/.zshrc or ~/.bashrc)
alias claude='claude --add-dir ~/code/claude-skills'
```

Reload your shell, run `claude`, and the skills below are available in every project.

To update later:

```bash
cd ~/code/claude-skills && git pull
```

> **`--add-dir` covers skills only.** It does **not** load this repo's output styles,
> so the `terse` skill will find no style to select. Either run `./install.sh` once in
> your clone as well, or symlink the style by hand — see
> [Output styles](#output-styles).

## Install (alternative: symlink into your personal skills dir)

If you would rather have the skills appear under `~/.claude/skills/` without the
alias, run the bundled installer. It symlinks each skill folder into place, **and
symlinks the output styles too**, so `git pull` keeps everything current.

```bash
git clone https://github.com/ninthspace/claude-skills.git ~/code/claude-skills
cd ~/code/claude-skills
./install.sh
```

> Note: symlink-based skill discovery is not formally documented by Anthropic. It
> works in practice, but if you hit issues, edit `install.sh` to copy (`cp -R`)
> instead of symlink — at the cost of re-running it after each `git pull`.

## Output styles

Output styles change how Claude *writes back to you*; they do not change what it
does. They install differently from skills, and the difference is easy to trip over:

- Claude Code reads output styles **only** from `~/.claude/output-styles/` or a
  project's `.claude/output-styles/`.
- `claude --add-dir` does **not** register output styles from the added directory —
  unlike skills, there is no auto-discovery path.

So `./install.sh` is the install route regardless of which option you chose for
skills. It symlinks every `.claude/output-styles/*.md` into
`~/.claude/output-styles/`. To do it by hand for a single style:

```bash
mkdir -p ~/.claude/output-styles
ln -sfn ~/code/claude-skills/.claude/output-styles/terse.md ~/.claude/output-styles/terse.md
```

Switch styles with `/output-style`, or — for `Terse` specifically — the
[`terse`](.claude/skills/terse/) skill, which flips the `outputStyle` key in your
settings for you. Symlinked output styles are honoured by Claude Code; as with
skills, swap the `ln -sfn` in `install.sh` for `cp` if your setup disagrees.

## Adding a skill

1. Create a branch.
2. Add `.claude/skills/<your-skill>/SKILL.md` (plus any supporting files).
3. Open a pull request. **All skills are reviewed before merge** — a skill can run
   bash and edit files on a teammate's machine, so review the scripts and hooks as
   you would any code.

See the [Claude Code skills docs](https://code.claude.com/docs/en/skills) for the
`SKILL.md` format.

## Adding an output style

1. Create a branch.
2. Add `.claude/output-styles/<name>.md` — a single file, with `name:` and
   `description:` front-matter. `install.sh` picks it up automatically (it globs the
   directory), so the installer needs no change.
3. Add a row to the **Included output styles** table above.
4. Open a pull request.

See the [output styles docs](https://code.claude.com/docs/en/output-styles) for the
front-matter fields, including `keep-coding-instructions`.

## Security

- **Review**: changes should land via reviewed PR; `git log` is the audit trail.
- **Runtime guardrails**: `settings.json` ships a `permissions.deny` list that
  Claude Code enforces regardless of what any skill attempts. Copy or merge it into
  your own `~/.claude/settings.json` (or your project's `.claude/settings.json`).
- **Pre-commit guard**: `.githooks/pre-commit` blocks any staged `ASD-STE100*`
  file — the `stakeholder-rewrite` standard is free but not redistributable, so it
  must never be committed here. `install.sh` activates it
  (`git config core.hooksPath .githooks`); if you install via `--add-dir` instead,
  run that command once in your clone to enable the hook.
