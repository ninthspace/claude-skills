# claude-skills

A collection of shared [Claude Code](https://code.claude.com) skills.

**Version 1.0.0** — see [CHANGELOG.md](CHANGELOG.md) for what's in this release.

Skills live under `.claude/skills/`, one folder per skill. Runtime behaviour is
constrained by the shared `settings.json` deny-list.

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
| [`code-to-uml`](.claude/skills/code-to-uml/) | Maps how a subject moves through one or more repositories from the real code, then builds a self-contained HTML artifact of hand-authored SVG UML activity and sequence diagrams (plus optional system-context and data-model panels). No Mermaid/CDN — the Artifact CSP blocks them. |
| [`stakeholder-rewrite`](.claude/skills/stakeholder-rewrite/) | Rewrites a technical document into a plain-language version for a non-technical audience — board, trustees, funders, service managers, the public — using ASD-STE100 Simplified Technical English as the discipline. Applies the STE rules that remove ambiguity and relaxes the maintenance-manual register a governance paper doesn't need. Labels output "plain-language, STE-informed" unless genuine compliance is checked, and preserves every figure, date, and caveat exactly. |

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

## Install (alternative: symlink into your personal skills dir)

If you would rather have the skills appear under `~/.claude/skills/` without the
alias, run the bundled installer. It symlinks each skill folder into place, so
`git pull` keeps them current.

```bash
git clone https://github.com/ninthspace/claude-skills.git ~/code/claude-skills
cd ~/code/claude-skills
./install.sh
```

> Note: symlink-based skill discovery is not formally documented by Anthropic. It
> works in practice, but if you hit issues, edit `install.sh` to copy (`cp -R`)
> instead of symlink — at the cost of re-running it after each `git pull`.

## Adding a skill

1. Create a branch.
2. Add `.claude/skills/<your-skill>/SKILL.md` (plus any supporting files).
3. Open a pull request. **All skills are reviewed before merge** — a skill can run
   bash and edit files on a teammate's machine, so review the scripts and hooks as
   you would any code.

See the [Claude Code skills docs](https://code.claude.com/docs/en/skills) for the
`SKILL.md` format.

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
