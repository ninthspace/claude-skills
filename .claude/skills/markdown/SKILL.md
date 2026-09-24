---
name: markdown
description: Produce HTML-safe markdown with correct hard line breaks. Use when creating or editing markdown files, writing documentation, drafting READMEs, producing release notes, or any time markdown will be rendered as HTML and visual line breaks must survive. Triggers on "write a markdown file", "create docs", "draft a README", "write release notes", ".md", or any prompt that results in a markdown artifact.
---

# Markdown Authoring Conventions

Two separate concerns make markdown render incorrectly as HTML, and each has its own fix.

**Structural**: Lists and headings need a blank line before them to be *recognised as lists/headings* by the renderer. Without that separator, `- item` reads as a literal dash inside the preceding paragraph (no bullets) and `## Heading` merges into prose. Fix: insert a blank line.

**Visual**: Within a single HTML block — a list item with multi-line content, or adjacent `**Key:** value` micro-metadata paragraphs — intended line breaks are collapsed. Fix: add two trailing spaces (`  `) to force a `<br>`.

## Structural rules (insert blank lines)

- Before a list item, insert a blank line when the previous line is non-blank and is not itself a list item or list continuation (and is not inside a code fence).
- Before a heading, insert a blank line when the previous line is non-blank (and is not inside a code fence).

## Visual rules (add two trailing spaces)

Add `  ` to a line when:

- The line is a list item or continuation, and the **immediately next** line is a continuation of the same list item (preserves visual breaks inside a single `<li>`).
- The line is a bold-key-with-colon (`**Label:** ...`) and the **immediately next** line is also a bold-key-with-colon (separates adjacent metadata fields in one paragraph).

Never insert hard breaks on:

- Blank lines.
- Heading lines themselves.
- Lines inside a fenced code block (```` ``` ```` or `~~~`).
- Table rows (lines starting with optional whitespace then `|`).
- The last content line of the file.
- Lines that already end with `  `, `<br>`, `<br/>`, `<br />`, or `\`.

Flowing paragraphs are left alone — that is the point of "targeted".

## Enforcement

An optional `PostToolUse` hook on `Write|Edit` (opt-in: registered in `settings.json` as described in `README.md`) runs the formatter against any `.md` file whose path contains `/docs/`. Do not assume it is active — author to the rules above, or run the formatter yourself after writing.

For `.md` files outside `docs/` (e.g. `CLAUDE.md`, plugin `SKILL.md`, root `README.md`), the hook does **not** fire. Apply the rule manually if the output will be rendered as HTML.

## Formatter

`scripts/hard_breaks.py` (in this skill's folder)

Usage:

- Stdin → stdout: `cat file.md | python3 scripts/hard_breaks.py`
- In place: `python3 scripts/hard_breaks.py path/to/file.md` (rewrites file)

The script is idempotent: a second run produces byte-identical output. Safe to invoke defensively.

## Hook

`scripts/hook.py` (in this skill's folder)

Entrypoint for Claude Code's `PostToolUse` event. Reads event JSON on stdin, filters for `Write|Edit` of `.md` paths containing `/docs/`, runs the formatter on the file in place, and always exits 0 (never blocks Claude).
