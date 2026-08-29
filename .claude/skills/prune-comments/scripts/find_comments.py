#!/usr/bin/env python3
"""
find_comments.py — find long comment blocks in first-party source, from the cwd down.

Reports every comment block of >= --min-lines consecutive comment lines, excluding
dependency trees (vendor/, node_modules/, ...), build output, and minified or
generated files. In a git repository the file set comes from `git ls-files`, so
.gitignore is honoured automatically; outside one, a static skip list is used.

This is a *heuristic* scanner, not a parser. It errs conservative: a line counts as a
comment only when the comment marker starts the line, so trailing comments, markers
inside string literals, and most false positives are simply never reported. Its job is
to point at blocks worth reading — the judgement about what to shorten or delete is
made against the code, not from this output.

Usage:
    python find_comments.py                      # cwd down, blocks of 4+ lines
    python find_comments.py app resources        # scoped to paths
    python find_comments.py --min-lines 5 --json
    python find_comments.py --only-flagged       # only blocks with an agent/artifact tell

Output (text) is one record per block:
    path:start-end  (N lines) [kind] [flags]
followed by the block, indented.
"""

import argparse
import fnmatch
import json
import os
import re
import subprocess
import sys

# ---------------------------------------------------------------------------
# What is not first-party source.
# ---------------------------------------------------------------------------

SKIP_DIRS = {
    ".git", ".hg", ".svn", ".idea", ".vscode", ".venv", "venv", "env",
    "vendor", "node_modules", "bower_components", "jspm_packages",
    "dist", "build", "out", "target", "coverage", "__pycache__",
    ".next", ".nuxt", ".svelte-kit", ".turbo", ".parcel-cache",
    "bootstrap/cache", "storage", "public/build", "public/hot",
    "Pods", "DerivedData", ".terraform", "site-packages",
}

# Path fragments that mean third-party or generated even when tracked in git.
SKIP_PATH_PARTS = (
    "/vendor/", "/node_modules/", "/third_party/", "/thirdparty/",
    "/dist/", "/build/", "/__pycache__/", "/site-packages/",
)

SKIP_FILE_GLOBS = (
    "*.min.js", "*.min.css", "*.min.mjs", "*-min.js", "*.bundle.js", "*.map",
    "*.lock", "*-lock.json", "*.pb.go", "*_pb2.py", "*_pb2_grpc.py", "*.g.dart",
    "*.generated.*", "*.designer.cs", "*.d.ts",
)

MAX_BYTES = 1_000_000        # bigger than this is not hand-written source
MAX_LINE_LEN = 2000          # a line this long means minified/generated

# ---------------------------------------------------------------------------
# Comment syntax by extension.
#   line:  markers that start a whole-line comment
#   block: (open, close) pairs
# ---------------------------------------------------------------------------

C_LIKE = {"line": ("//",), "block": (("/*", "*/"),)}
HASH = {"line": ("#",), "block": ()}

SYNTAX = {
    ".php": {"line": ("//", "#"), "block": (("/*", "*/"),)},
    ".blade.php": {"line": ("//", "#"), "block": (("/*", "*/"), ("{{--", "--}}"), ("<!--", "-->"))},
    ".js": C_LIKE, ".mjs": C_LIKE, ".cjs": C_LIKE, ".jsx": C_LIKE,
    ".ts": C_LIKE, ".tsx": C_LIKE, ".java": C_LIKE, ".kt": C_LIKE, ".kts": C_LIKE,
    ".c": C_LIKE, ".h": C_LIKE, ".cpp": C_LIKE, ".cc": C_LIKE, ".hpp": C_LIKE,
    ".cs": C_LIKE, ".go": C_LIKE, ".rs": C_LIKE, ".swift": C_LIKE, ".m": C_LIKE,
    ".mm": C_LIKE, ".scala": C_LIKE, ".dart": C_LIKE, ".groovy": C_LIKE,
    ".proto": C_LIKE, ".sol": C_LIKE, ".gradle": C_LIKE,
    ".css": {"line": (), "block": (("/*", "*/"),)},
    ".scss": C_LIKE, ".sass": C_LIKE, ".less": C_LIKE,
    ".vue": {"line": ("//",), "block": (("/*", "*/"), ("<!--", "-->"))},
    ".svelte": {"line": ("//",), "block": (("/*", "*/"), ("<!--", "-->"))},
    ".html": {"line": (), "block": (("<!--", "-->"),)},
    ".htm": {"line": (), "block": (("<!--", "-->"),)},
    ".xml": {"line": (), "block": (("<!--", "-->"),)},
    ".twig": {"line": (), "block": (("{#", "#}"), ("<!--", "-->"))},
    ".py": {"line": ("#",), "block": (('"""', '"""'), ("'''", "'''"))},
    ".rb": {"line": ("#",), "block": (("=begin", "=end"),)},
    ".sh": HASH, ".bash": HASH, ".zsh": HASH, ".fish": HASH,
    ".yml": HASH, ".yaml": HASH, ".toml": HASH, ".ini": HASH,
    ".tf": HASH, ".tfvars": HASH, ".pl": HASH, ".pm": HASH, ".r": HASH,
    ".ex": HASH, ".exs": HASH, ".cr": HASH, ".nim": HASH,
    ".sql": {"line": ("--",), "block": (("/*", "*/"),)},
    ".lua": {"line": ("--",), "block": (("--[[", "]]"),)},
    ".hs": {"line": ("--",), "block": (("{-", "-}"),)},
    ".erl": {"line": ("%",), "block": ()},
    ".tex": {"line": ("%",), "block": ()},
    ".el": {"line": (";",), "block": ()},
    ".clj": {"line": (";",), "block": ()},
}

BASENAME_SYNTAX = {
    "dockerfile": HASH, "makefile": HASH, "rakefile": HASH,
    "gemfile": HASH, "vagrantfile": HASH, "procfile": HASH,
}

# ---------------------------------------------------------------------------
# Flags: what a block might be, worked out from its text.
# ---------------------------------------------------------------------------

AGENT_RE = re.compile(
    r"\b(claude|anthropic|copilot|cursor\.?(so|ai)?|chatgpt|openai|gpt-[0-9]|codex|"
    r"aider|windsurf|devin|ai[- ]generated|generated by ai|llm|coding agent|"
    r"ai assistant|assistant note|note to (the )?(ai|agent|assistant|model)|"
    r"system reminder|do not edit this (file|block) if you are|"
    r"agents?\.md|claude\.md|cursorrules)\b",
    re.I,
)

SCAFFOLD_RE = re.compile(
    r"(this (file|class|method|function) (was |is )?(auto[- ]?)?generated|"
    r"add your (routes|code|logic) here|"
    r"you may (add|register|define) your|"
    r"here is where you (can|should)|"
    r"feel free to|"
    r"placeholder|"
    r"^\s*todo:?\s*(implement|fill|add)\b|"
    r"step [0-9]+ of [0-9]+|"
    r"as (requested|discussed|per your request)|"
    r"(changed|updated|fixed|refactored) (this|the) (above|below|following))",
    re.I,
)

PRESERVE_RE = re.compile(
    r"(copyright|\(c\)\s*[0-9]{4}|spdx-license|licen[cs]e|all rights reserved|"
    r"@license|@copyright|"
    r"eslint-disable|prettier-ignore|stylelint-disable|"
    r"phpcs:|phpstan-|psalm-|@codeCoverageIgnore|noqa|type:\s*ignore|"
    r"ts-expect-error|@ts-ignore|nosec|gosec:|golangci|"
    r"@template|@phpstan|@psalm|@deprecated|@throws|@var\s+|@param\s+[\\\w|\[\]<>]+\s+\$)",
    re.I,
)

CODE_LIKE_RE = re.compile(
    r"(;\s*$|^\s*(if|for|foreach|while|return|function|def|class|const|let|var|"
    r"public|private|protected|import|from|use)\b.*[({:]|=>|\{\s*$|\}\s*$|\$\w+\s*=)",
)


def flags_for(body_lines):
    """Classify a block from its text. Flags are hints for triage, not verdicts."""
    text = "\n".join(body_lines)
    out = []
    if AGENT_RE.search(text):
        out.append("agent")
    if SCAFFOLD_RE.search(text):
        out.append("scaffold")
    if PRESERVE_RE.search(text):
        out.append("preserve")
    stripped = [ln for ln in body_lines if ln.strip()]
    if stripped and sum(bool(CODE_LIKE_RE.search(ln)) for ln in stripped) >= max(2, len(stripped) // 2):
        out.append("commented-code")
    return out


# ---------------------------------------------------------------------------
# File discovery.
# ---------------------------------------------------------------------------

def git_files(root):
    try:
        res = subprocess.run(
            ["git", "ls-files", "-co", "--exclude-standard"],
            cwd=root, capture_output=True, text=True, timeout=30,
        )
    except (OSError, subprocess.SubprocessError):
        return None
    if res.returncode != 0:
        return None
    return [os.path.join(root, p) for p in res.stdout.splitlines() if p]


def walk_files(root):
    found = []
    for cur, dirs, names in os.walk(root):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS and not d.startswith(".")]
        for n in names:
            found.append(os.path.join(cur, n))
    return found


def syntax_for(path):
    base = os.path.basename(path).lower()
    if base.endswith(".blade.php"):
        return SYNTAX[".blade.php"]
    if base in BASENAME_SYNTAX:
        return BASENAME_SYNTAX[base]
    _, ext = os.path.splitext(base)
    return SYNTAX.get(ext)


def is_skippable(path):
    norm = "/" + os.path.relpath(path).replace(os.sep, "/")
    if any(part in norm for part in SKIP_PATH_PARTS):
        return True
    if any(seg in SKIP_DIRS for seg in norm.split("/")[:-1]):
        return True
    base = os.path.basename(path)
    return any(fnmatch.fnmatch(base, g) for g in SKIP_FILE_GLOBS)


def collect(paths):
    """Resolve the argument paths into a list of first-party source files."""
    roots = paths or ["."]
    candidates = []
    for root in roots:
        if os.path.isfile(root):
            candidates.append(root)
            continue
        if not os.path.isdir(root):
            print(f"warning: skipping {root!r} (not a file or directory)", file=sys.stderr)
            continue
        listed = git_files(root)
        candidates.extend(listed if listed is not None else walk_files(root))

    files, seen = [], set()
    for p in candidates:
        real = os.path.normpath(p)
        if real in seen or is_skippable(real) or syntax_for(real) is None:
            continue
        seen.add(real)
        try:
            if os.path.getsize(real) > MAX_BYTES:
                continue
        except OSError:
            continue
        files.append(real)
    return sorted(files)


# ---------------------------------------------------------------------------
# Block detection.
# ---------------------------------------------------------------------------

def find_blocks(lines, syntax, min_lines):
    """Yield (start, end, kind) 1-indexed inclusive comment blocks of min_lines or more."""
    blocks = []
    line_markers = syntax["line"]
    block_pairs = syntax["block"]
    i = 0
    n = len(lines)

    while i < n:
        raw = lines[i]
        s = raw.strip()

        if i == 0 and s.startswith("#!"):
            i += 1
            continue

        opened = next(((o, c) for o, c in block_pairs if s.startswith(o)), None)
        if opened:
            o, c = opened
            # A one-line block comment closes on the same line after its opener.
            if s.find(c, len(o)) != -1:
                i += 1
                continue
            start = i
            i += 1
            while i < n and c not in lines[i]:
                i += 1
            end = min(i, n - 1)
            kind = "docblock" if s.startswith("/**") or s.startswith('"""') else "block"
            if end - start + 1 >= min_lines:
                blocks.append((start + 1, end + 1, kind))
            i += 1
            continue

        if line_markers and any(s.startswith(m) for m in line_markers):
            start = i
            while i < n:
                nxt = lines[i].strip()
                if not any(nxt.startswith(m) for m in line_markers):
                    break
                i += 1
            end = i - 1
            if end - start + 1 >= min_lines:
                blocks.append((start + 1, end + 1, "line"))
            continue

        i += 1

    return blocks


def scan(files, min_lines):
    results = []
    for path in files:
        syntax = syntax_for(path)
        try:
            with open(path, "r", encoding="utf-8", errors="replace") as fh:
                lines = fh.read().splitlines()
        except OSError:
            continue
        if any(len(ln) > MAX_LINE_LEN for ln in lines[:200]):
            continue
        for start, end, kind in find_blocks(lines, syntax, min_lines):
            body = lines[start - 1:end]
            results.append({
                "file": os.path.relpath(path),
                "start": start,
                "end": end,
                "lines": end - start + 1,
                "kind": kind,
                "flags": flags_for(body),
                "text": "\n".join(body),
            })
    return results


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("paths", nargs="*", help="files or directories (default: cwd)")
    ap.add_argument("--min-lines", type=int, default=4, help="minimum block length to report (default 4)")
    ap.add_argument("--json", action="store_true", help="machine-readable output")
    ap.add_argument("--only-flagged", action="store_true", help="only blocks with a flag other than 'preserve'")
    ap.add_argument("--exclude", action="append", default=[], metavar="GLOB",
                    help="additional path glob to skip (repeatable)")
    args = ap.parse_args()

    files = collect(args.paths)
    if args.exclude:
        files = [f for f in files if not any(fnmatch.fnmatch(f, g) for g in args.exclude)]

    results = scan(files, args.min_lines)
    if args.only_flagged:
        results = [r for r in results if [f for f in r["flags"] if f != "preserve"]]

    if args.json:
        json.dump({"files_scanned": len(files), "blocks": results}, sys.stdout, indent=2)
        sys.stdout.write("\n")
        return

    if not results:
        print(f"No comment blocks of {args.min_lines}+ lines in {len(files)} files.")
        return

    total = 0
    for r in results:
        total += r["lines"]
        flag_str = (" [" + ", ".join(r["flags"]) + "]") if r["flags"] else ""
        print(f'{r["file"]}:{r["start"]}-{r["end"]}  ({r["lines"]} lines) [{r["kind"]}]{flag_str}')
        for ln in r["text"].splitlines():
            print(f"    {ln}")
        print()
    print(f"{len(results)} blocks, {total} comment lines, across {len(files)} files scanned.")


if __name__ == "__main__":
    main()
