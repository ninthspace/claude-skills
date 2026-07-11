#!/usr/bin/env python3
"""Structural + targeted hard-break formatter for markdown.

Two concerns:

  1. Structural — a list or heading that follows a non-blank line without a
     blank line between is not recognised as a list/heading by CommonMark
     renderers (the dashes render literally, headings merge into paragraphs).
     Insert a blank line in those positions.

  2. Visual — within a single HTML block (list item with continuation, or
     adjacent bold-key-with-colon paragraphs), intended visual line breaks
     are collapsed unless the line ends with two trailing spaces. Add them
     where needed.

Rules:

  - Insert blank line BEFORE a list item when the previous line is non-blank
    and is NOT itself a list item or list continuation (not in a code fence).
  - Insert blank line BEFORE a heading when the previous line is non-blank
    (not in a code fence).
  - Add `  ` to a list item line whose IMMEDIATELY next line is a
    continuation of the same item.
  - Add `  ` to a continuation line whose IMMEDIATELY next line is another
    continuation.
  - Add `  ` to a bold-key-with-colon line (`**Label:** ...` or
    `**Label**: ...`) whose IMMEDIATELY next line is another such line.

Never modify: code fence delimiters, content inside fences, table rows,
heading lines themselves, the last content line, or lines already ending
in `  `, `<br>`, `<br/>`, `<br />`, or `\\`.

Idempotent: a second run produces byte-identical output.

Usage:
  python3 hard_breaks.py               # stdin -> stdout
  python3 hard_breaks.py path/to.md    # rewrite file in place
"""
import re
import sys

FENCE_RE = re.compile(r"^\s*(```|~~~)")
HEADING_RE = re.compile(r"^\s{0,3}#{1,6}(\s|$)")
LIST_RE = re.compile(r"^(\s*)([-*+]|\d+\.)(\s+|$)")
TABLE_RE = re.compile(r"^\s*\|")
BOLD_KEY_RE = re.compile(r"^\s*\*\*[^*]+:\*\*|^\s*\*\*[^*]+\*\*\s*:")
HAS_BREAK_RE = re.compile(r"(  +$|<br\s*/?>\s*$|\\$)")


def format_markdown(text: str) -> str:
    lines = text.split("\n")
    n = len(lines)
    if n == 0:
        return text

    # Pass 1: fenced code block regions.
    in_fence = [False] * n
    fence_open = False
    for i, line in enumerate(lines):
        if FENCE_RE.match(line):
            in_fence[i] = True
            fence_open = not fence_open
        else:
            in_fence[i] = fence_open

    # Pass 2: list-item and list-continuation flags.
    is_list_item = [False] * n
    is_list_cont = [False] * n
    current_list_indent = None
    for i, line in enumerate(lines):
        if in_fence[i]:
            current_list_indent = None
            continue
        stripped = line.strip()
        if stripped == "":
            continue
        indent = len(line) - len(line.lstrip())
        if LIST_RE.match(line):
            current_list_indent = indent
            is_list_item[i] = True
        elif current_list_indent is not None and indent > current_list_indent:
            is_list_cont[i] = True
        else:
            current_list_indent = None

    # Last content line — never modify.
    last_content_idx = n - 1
    while last_content_idx >= 0 and lines[last_content_idx].strip() == "":
        last_content_idx -= 1

    def needs_break(i: int) -> bool:
        if i >= last_content_idx:
            return False
        line = lines[i]
        if in_fence[i] or FENCE_RE.match(line):
            return False
        if line.strip() == "":
            return False
        if HEADING_RE.match(line):
            return False
        if TABLE_RE.match(line):
            return False
        if HAS_BREAK_RE.search(line):
            return False
        # Within-list: item/continuation followed immediately by another
        # continuation of the same item.
        if is_list_item[i] or is_list_cont[i]:
            if i + 1 < n and is_list_cont[i + 1]:
                return True
        # Adjacent bold-key-with-colon metadata pairs.
        if BOLD_KEY_RE.match(line):
            if i + 1 < n and BOLD_KEY_RE.match(lines[i + 1]):
                return True
        return False

    def needs_blank_before(i: int) -> bool:
        if i == 0:
            return False
        prev = lines[i - 1]
        if prev.strip() == "":
            return False
        if in_fence[i] or in_fence[i - 1]:
            return False
        if FENCE_RE.match(prev) or FENCE_RE.match(lines[i]):
            return False
        # Insert blank before a list that isn't already inside a list context.
        if is_list_item[i]:
            if is_list_item[i - 1] or is_list_cont[i - 1]:
                return False
            return True
        # Insert blank before a heading.
        if HEADING_RE.match(lines[i]):
            return True
        return False

    out = []
    for i, line in enumerate(lines):
        if needs_blank_before(i):
            out.append("")
        if needs_break(i):
            out.append(line.rstrip() + "  ")
        else:
            out.append(line)
    return "\n".join(out)


def main(argv):
    if len(argv) == 1:
        text = sys.stdin.read()
        sys.stdout.write(format_markdown(text))
        return 0
    if len(argv) == 2:
        path = argv[1]
        try:
            with open(path, "r", encoding="utf-8") as f:
                text = f.read()
        except (OSError, UnicodeDecodeError):
            return 0  # never block — unreadable file is a no-op
        formatted = format_markdown(text)
        if formatted != text:
            with open(path, "w", encoding="utf-8") as f:
                f.write(formatted)
        return 0
    sys.stderr.write("Usage: hard_breaks.py [FILE]\n")
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv))
