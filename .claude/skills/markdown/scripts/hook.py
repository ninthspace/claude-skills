#!/usr/bin/env python3
"""PostToolUse hook entrypoint for the markdown skill.

Claude Code invokes this script after every tool call (because the matcher
is `Write|Edit`). The event JSON is delivered on stdin; we inspect it, and:

  - no-op (exit 0) if the tool is not Write or Edit
  - no-op (exit 0) if the file path doesn't end in .md
  - no-op (exit 0) if the file path doesn't contain `/docs/`
  - otherwise, run hard_breaks.py against the file in place

We always exit 0. The hook must never block Claude, even on internal errors.
"""
import json
import os
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
FORMATTER = os.path.join(HERE, "hard_breaks.py")


def main() -> int:
    try:
        event = json.load(sys.stdin)
    except (ValueError, OSError):
        return 0

    tool_name = event.get("tool_name")
    if tool_name not in ("Write", "Edit"):
        return 0

    tool_input = event.get("tool_input") or {}
    file_path = tool_input.get("file_path")
    if not file_path or not isinstance(file_path, str):
        return 0
    if not file_path.endswith(".md"):
        return 0
    if "/docs/" not in file_path:
        return 0
    if not os.path.isfile(file_path):
        return 0

    try:
        subprocess.run(
            [sys.executable, FORMATTER, file_path],
            check=False,
            timeout=10,
        )
    except (OSError, subprocess.TimeoutExpired):
        pass
    return 0


if __name__ == "__main__":
    sys.exit(main())
