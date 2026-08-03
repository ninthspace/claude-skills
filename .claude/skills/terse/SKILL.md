---
name: terse
description: Switch the active Claude Code output style to or from "Terse". Use when the user says "/terse", "go terse", "terse mode", "turn off terse", "back to normal/default output", or otherwise asks to toggle the answer-first Terse style on or off.
---

# terse — toggle the Terse output style

The **Terse** output style is answer-first, minimal-prose. It ships alongside this
skill and is installed to `~/.claude/output-styles/terse.md` by the repo's
`install.sh`. This skill flips it on or off by editing the `outputStyle` key in the
user's Claude Code settings. "Off" means the built-in `default` style.

## Resolve the argument

The invocation may carry an argument:

- `on`, `terse`, `enable` → set Terse.
- `off`, `default`, `normal`, `disable` → set default.
- anything else, or **no argument** → **toggle** based on the current value.

## Procedure

1. **Find where `outputStyle` currently lives.** Check, in this order, and use the first file that already contains an `outputStyle` key:
   - `./.claude/settings.local.json` (project, gitignored — preferred)
   - `./.claude/settings.json` (project, checked in)
   - `~/.claude/settings.json` (global)

   If no file has the key yet, treat the current style as `default` and write the key to `./.claude/settings.local.json` if that file exists, otherwise to `~/.claude/settings.json`.

2. **Determine the target value.**
   - Explicit `on`/`off` argument → `"Terse"` / `"default"`.
   - Toggle → if current is `"Terse"`, target `"default"`; otherwise target `"Terse"`.

3. **If already at the target**, do nothing and say so (e.g. `Already Terse.`). Stop.

4. **Edit the JSON in place** with the Edit tool, changing only the `outputStyle` value (or adding the key). Do not reformat or reorder the rest of the file. Preserve indentation and trailing structure exactly.

   - Setting Terse: `"outputStyle": "Terse"`
   - Setting default: `"outputStyle": "default"`

5. **Confirm in one line**, naming the file touched and the new state — e.g. `Terse on (.claude/settings.local.json).` or `Terse off — default style (.claude/settings.local.json).`

## Notes

- The change is picked up on the next turn; if the user doesn't see it apply immediately, they can confirm with `/output-style`.
- Only ever touch the `outputStyle` key. Never edit `permissions`, `env`, hooks, or any other setting from this skill.
- `"default"` is the correct value for "off" — it is Claude Code's built-in explanatory style. Do not delete the key or invent other style names.
- If setting Terse reports that the style is unknown, the output style has not been installed. `claude --add-dir` does **not** load output styles — run the repo's `./install.sh`, or symlink `.claude/output-styles/terse.md` into `~/.claude/output-styles/` by hand.
