# CLAUDE.md — claude-skills

Shared Claude Code skills. Each skill is a folder
under `.claude/skills/<skill-name>/` containing a `SKILL.md` (uppercase) plus any
supporting files. The repo also ships **output styles** — single `.md` files under
`.claude/output-styles/` — which install by a different route (see below).
See `README.md` for the install and distribution model.

## Keep the README skill list in sync

The **Included skills** table in `README.md` is the human-facing index of what this
repo ships. It must always match the skills actually present under
`.claude/skills/`. Whenever you add, remove, or change a skill, update that table in
the **same change** — never leave it stale.

- **Skill added** — add a row: `` | [`<name>`](.claude/skills/<name>/) | <one-line summary> | ``.
  Derive the summary from the skill's own `description:` frontmatter (condense it;
  don't paste the whole trigger list).
- **Skill removed** — delete its row.
- **Skill renamed** — update both the link text and the path in its row.
- **Skill's purpose changed** — re-derive the summary from the updated frontmatter
  so the row still reflects what the skill does.

After editing, sanity-check that every folder under `.claude/skills/` has exactly
one table row and vice versa.

The same rule applies to the **Included output styles** table and
`.claude/output-styles/*.md`: one file, one row, kept in sync in the same change.

## Output styles

- One style per file: `.claude/output-styles/<name>.md`, with `name:` and
  `description:` front-matter. The `name:` value is what `outputStyle` in a settings
  file must match — it is case-sensitive and need not match the filename (`terse.md`
  declares `name: Terse`).
- **Output styles do not share the skills' discovery mechanism.** Claude Code reads
  them only from `~/.claude/output-styles/` or a project `.claude/output-styles/`,
  and `claude --add-dir` does **not** register them. `install.sh` symlinks them, and
  that is the only install path — a skill that selects a style is broken without it.
- `install.sh` globs `.claude/output-styles/*.md`, so adding a style needs no
  installer change. Any change to *how* styles install must be reflected in the
  README's **Output styles** section in the same change.
- A skill that reads or sets `outputStyle` must touch that key only, and must treat
  the built-in `default` as "off" rather than deleting the key.

## Conventions

- One skill per folder; the skill file is `SKILL.md` (uppercase) — lowercase
  `skill.md` is discovered on macOS's case-insensitive filesystem but fails on
  Linux. Rename to `SKILL.md` when importing a skill that uses lowercase.
- Skills are distributed by symlinking the repo's folders into `~/.claude/skills/`
  (`./install.sh`) or via `claude --add-dir`. The repo is the source of truth.
- A skill that ships an executable script or a hook (`scripts/*.py`, hook
  entrypoints) gets reviewed as code before merge — it can run on a teammate's
  machine. Note any opt-in hook registration the skill expects in `settings.json`.
- Runtime guardrails live in `settings.json` (`permissions.deny`). Don't weaken
  them to make a skill work.

## Git

- Don't `git push` or open PRs unless asked. Additions land via a branch and a
  reviewed pull request (see `README.md`).
