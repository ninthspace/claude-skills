# markdown skill — optional formatter hook

This skill ships a `PostToolUse` hook that automatically reformats markdown for
HTML-safe rendering (blank lines before lists/headings, two-space hard breaks).
It is **opt-in**: the hook does nothing until you register it in a `settings.json`
`hooks` block. Installing the skill alone does not activate it.

## What the hook does

After every `Write` or `Edit`, `scripts/hook.py` runs `scripts/hard_breaks.py`
against the file **in place** — but only when the file path:

- ends in `.md`, **and**
- contains `/docs/` in its path.

Any other file is ignored. The formatter is idempotent (a second run is a no-op)
and the hook always exits 0, so it never blocks Claude even on error. Files
outside `docs/` (e.g. `README.md`, `CLAUDE.md`) are never touched — apply the
conventions in `SKILL.md` by hand for those.

## How to enable it

Add a `PostToolUse` entry to your `settings.json` (`~/.claude/settings.json` for
all projects, or a project's `.claude/settings.json` to scope it). Merge into an
existing `hooks` block if you have one:

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Write|Edit",
        "hooks": [
          {
            "type": "command",
            "command": "python3 <PATH-TO>/markdown/scripts/hook.py"
          }
        ]
      }
    ]
  }
}
```

Replace `<PATH-TO>` with wherever this skill resolves on your machine — which
depends on how you installed the repo:

- **Symlink install (`./install.sh`)** — the skill is linked into your personal
  skills dir, so use:
  `~/.claude/skills/markdown/scripts/hook.py`
- **`--add-dir` install** — the skill stays in your clone, so point at the clone,
  e.g.:
  `~/code/claude-skills/.claude/skills/markdown/scripts/hook.py`

Use an absolute path (or a `python3` on your `PATH`). If you have multiple Python
installs, name the interpreter explicitly, e.g. `/opt/homebrew/bin/python3`.

Restart Claude Code (or start a new session) after editing `settings.json`.

## Verify it works

```bash
# from anywhere — feed it a deliberately un-spaced list
printf 'Intro line\n- a\n- b\n' | python3 <PATH-TO>/markdown/scripts/hard_breaks.py
```

You should see a blank line inserted before `- a`. To confirm the hook itself
fires, edit any `.md` file under a `docs/` directory in a project and check that
list/heading spacing is normalised on save.

## Disable it

Remove the `PostToolUse` entry from `settings.json` (or delete just the command
line if you share the block with other hooks), then restart Claude Code.
