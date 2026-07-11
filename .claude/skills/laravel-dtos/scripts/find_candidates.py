#!/usr/bin/env python3
"""
find_candidates.py — surface DTO refactoring candidates in a PHP/Laravel codebase.

This is a *heuristic* scanner, not a parser. Its job is to point you at places worth
looking, not to make the refactoring decision for you. It flags two smells:

  1. Long parameter list  — a single signature with >= --min-params parameters.
  2. Data clump           — the same group of >= 2 parameters that travels together
                            across >= --min-occurrences distinct signatures. A clump
                            is the strongest signal that a concept is missing: the
                            cluster *is* the concept.

Apply judgment to the output. Not every long list wants a DTO (it may want the method
split up), and not every clump is a real concept (see SKILL.md, "Identify the concept").

Usage:
    python find_candidates.py app/Services app/Actions
    python find_candidates.py app --min-params 4 --min-occurrences 3
    python find_candidates.py app --json > candidates.json

Notes:
  - Pass any mix of files, directories, or glob-friendly paths. Directories are walked
    recursively for *.php. vendor/, node_modules/, and storage/ are skipped by default.
  - Constructor-promoted params (public readonly Foo $bar) are handled.
  - Closures (`function (`) and arrow functions are ignored — only named functions
    and methods are considered, since those are what you refactor.
"""

import argparse
import json
import os
import re
import sys
from collections import defaultdict
from itertools import combinations

FUNC_RE = re.compile(r"\bfunction\s+(\w+)\s*\(")
VAR_RE = re.compile(r"\$(\w+)")
SKIP_DIRS = {"vendor", "node_modules", "storage", ".git", "bootstrap/cache"}

# Parameter names so generic that their co-occurrence rarely signals a real concept.
# Clumps consisting *entirely* of these are demoted, not dropped (judgment still applies).
NOISE_NAMES = {"request", "data", "options", "attributes", "args", "params", "context"}


def gather_php_files(paths):
    files = []
    for p in paths:
        if os.path.isfile(p) and p.endswith(".php"):
            files.append(p)
        elif os.path.isdir(p):
            for root, dirs, names in os.walk(p):
                dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
                for n in names:
                    if n.endswith(".php"):
                        files.append(os.path.join(root, n))
        else:
            # treat as a path that may not exist; warn but continue
            print(f"warning: skipping {p!r} (not a .php file or directory)",
                  file=sys.stderr)
    return sorted(set(files))


def extract_balanced(text, open_idx):
    """Given index of an opening '(', return (inner_string, index_after_close).

    Respects nested (), [], and single/double quoted strings so default values
    like `['a' => fn() => 1]` don't trip the splitter."""
    depth = 0
    i = open_idx
    n = len(text)
    quote = None
    while i < n:
        c = text[i]
        if quote:
            if c == "\\":
                i += 2
                continue
            if c == quote:
                quote = None
        elif c in "\"'":
            quote = c
        elif c in "([{":
            depth += 1
        elif c in ")]}":
            depth -= 1
            if depth == 0:
                return text[open_idx + 1:i], i + 1
        i += 1
    return None, n  # unbalanced; bail


def split_top_level(params):
    """Split a raw parameter string on top-level commas only."""
    out, depth, quote, cur = [], 0, None, []
    i = 0
    while i < len(params):
        c = params[i]
        if quote:
            cur.append(c)
            if c == "\\":
                if i + 1 < len(params):
                    cur.append(params[i + 1])
                    i += 2
                    continue
            elif c == quote:
                quote = None
        elif c in "\"'":
            quote = c
            cur.append(c)
        elif c in "([{":
            depth += 1
            cur.append(c)
        elif c in ")]}":
            depth -= 1
            cur.append(c)
        elif c == "," and depth == 0:
            out.append("".join(cur))
            cur = []
        else:
            cur.append(c)
        i += 1
    if "".join(cur).strip():
        out.append("".join(cur))
    return [p.strip() for p in out if p.strip()]


def line_of(text, idx):
    return text.count("\n", 0, idx) + 1


def param_name(raw):
    m = VAR_RE.search(raw)
    return m.group(1) if m else None


def param_type(raw):
    """Best-effort type hint preceding the variable, stripping visibility/readonly."""
    before = raw.split("$", 1)[0]
    tokens = [t for t in before.replace("...", "").split()
              if t not in {"public", "protected", "private", "readonly", "&"}]
    return " ".join(tokens).strip() or None


def scan_file(path):
    """Return list of signature dicts for a file."""
    try:
        with open(path, "r", encoding="utf-8", errors="replace") as fh:
            text = fh.read()
    except OSError as e:
        print(f"warning: could not read {path}: {e}", file=sys.stderr)
        return []

    sigs = []
    for m in FUNC_RE.finditer(text):
        name = m.group(1)
        open_idx = m.end() - 1  # position of '('
        inner, _ = extract_balanced(text, open_idx)
        if inner is None:
            continue
        raw_params = split_top_level(inner)
        params = []
        for rp in raw_params:
            pn = param_name(rp)
            if pn:
                params.append({"name": pn, "type": param_type(rp), "raw": rp})
        if not params:
            continue
        sigs.append({
            "file": path,
            "line": line_of(text, m.start()),
            "function": name,
            "params": params,
            "param_names": [p["name"] for p in params],
            "arity": len(params),
        })
    return sigs


def find_clumps(sigs, min_occurrences):
    """Count co-occurring parameter-name groups (size 2 and 3) across signatures."""
    combo_hits = defaultdict(list)  # frozenset(names) -> [sig, ...]
    for sig in sigs:
        names = sorted(set(sig["param_names"]))
        if len(names) < 2:
            continue
        sizes = {2, 3}
        if 2 <= len(names) <= 6:
            sizes.add(len(names))  # whole-signature set, so recurring full lists collapse
        for size in sizes:
            for combo in combinations(names, size):
                combo_hits[frozenset(combo)].append(sig)

    clumps = []
    for combo, hit_sigs in combo_hits.items():
        # distinct signatures (file+line+function), not raw duplicates
        seen = {}
        for s in hit_sigs:
            seen[(s["file"], s["line"], s["function"])] = s
        if len(seen) >= min_occurrences:
            clumps.append({"names": sorted(combo), "sites": list(seen.values())})

    # Prefer larger clumps: drop a 2-combo if a 3-combo superset has >= as many sites.
    by_names = {frozenset(c["names"]): len(c["sites"]) for c in clumps}
    kept = []
    for c in clumps:
        names = frozenset(c["names"])
        dominated = any(
            names < other and other_count >= len(c["sites"])
            for other, other_count in by_names.items()
        )
        if not dominated:
            kept.append(c)

    def noisiness(c):
        return all(n in NOISE_NAMES for n in c["names"])

    kept.sort(key=lambda c: (noisiness(c), -len(c["sites"]), -len(c["names"])))
    return kept


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("paths", nargs="+", help="files, directories, or scope to scan")
    ap.add_argument("--min-params", type=int, default=4,
                    help="flag signatures with at least this many params (default 4)")
    ap.add_argument("--min-occurrences", type=int, default=2,
                    help="a clump must appear in at least this many signatures (default 2)")
    ap.add_argument("--json", action="store_true", help="emit JSON instead of a report")
    args = ap.parse_args()

    files = gather_php_files(args.paths)
    sigs = []
    for f in files:
        sigs.extend(scan_file(f))

    long_lists = sorted(
        [s for s in sigs if s["arity"] >= args.min_params],
        key=lambda s: -s["arity"],
    )
    clumps = find_clumps(sigs, args.min_occurrences)

    if args.json:
        json.dump({
            "scanned_files": len(files),
            "scanned_signatures": len(sigs),
            "long_parameter_lists": long_lists,
            "data_clumps": clumps,
        }, sys.stdout, indent=2)
        sys.stdout.write("\n")
        return

    print(f"Scanned {len(files)} file(s), {len(sigs)} named signature(s).\n")

    print(f"== DATA CLUMPS (params that travel together in >= {args.min_occurrences} "
          f"signatures) ==")
    if not clumps:
        print("  none found\n")
    else:
        print("  These are the strongest DTO candidates — each clump is likely a "
              "missing concept.\n")
        for c in clumps:
            tag = "  (generic names — verify it's a real concept)" \
                if all(n in NOISE_NAMES for n in c["names"]) else ""
            print(f"  • {{{', '.join('$' + n for n in c['names'])}}}  "
                  f"— {len(c['sites'])} sites{tag}")
            for s in c["sites"][:8]:
                print(f"      {s['file']}:{s['line']}  {s['function']}()")
            if len(c["sites"]) > 8:
                print(f"      … and {len(c['sites']) - 8} more")
            print()

    print(f"== LONG PARAMETER LISTS (>= {args.min_params} params) ==")
    if not long_lists:
        print("  none found")
    else:
        for s in long_lists:
            names = ", ".join("$" + n for n in s["param_names"])
            print(f"  • {s['file']}:{s['line']}  {s['function']}()  "
                  f"[{s['arity']} params: {names}]")
    print()


if __name__ == "__main__":
    main()
