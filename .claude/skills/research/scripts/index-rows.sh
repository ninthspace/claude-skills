#!/usr/bin/env bash
# Emit one markdown table row per research session, newest first.
# Read-only: prints to stdout and touches nothing. The skill writes INDEX.md
# from this output with the Write tool.
#
# Columns: Date | Topic | Status | Updated | Tags | Directory
#
# Usage: index-rows.sh [notebook-root]      (default: current directory)
#
# The notebook root is the directory holding the YYYY-MM-DD-* session
# directories and INDEX.md. Pass it explicitly — the skill is installed outside
# the notebook, so the current directory is usually somewhere else entirely.

set -uo pipefail

root="${1:-.}"

if [ ! -d "$root" ]; then
  echo "index-rows.sh: not a directory: $root" >&2
  exit 1
fi

cd "$root" || exit 1

field() {
  # field <file> <key> — first matching top-level front-matter key, value trimmed.
  # Pipes are stripped so a value can never break the table.
  grep -m1 "^$2:[[:space:]]*" "$1" 2>/dev/null \
    | cut -d: -f2- \
    | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
    | tr -d '|'
}

shopt -s nullglob
sessions=(2[0-9][0-9][0-9]-[0-1][0-9]-[0-3][0-9]-*/)

if [ "${#sessions[@]}" -eq 0 ]; then
  echo "| — | *No sessions yet* | — | — | — | — |"
  exit 0
fi

# Directory names begin with the ISO date, so a reverse lexical sort is newest first.
IFS=$'\n' sessions=($(printf '%s\n' "${sessions[@]}" | sort -r))
unset IFS

for dir in "${sessions[@]}"; do
  name="${dir%/}"
  readme="$name/README.md"

  if [ ! -f "$readme" ]; then
    printf '| %s | *(no README.md)* | ? | — | — | `%s` |\n' "${name:0:10}" "$name"
    continue
  fi

  topic=$(field "$readme" topic)
  status=$(field "$readme" status)
  updated=$(field "$readme" updated)
  tags=$(field "$readme" tags | tr -d '[]')

  printf '| %s | %s | %s | %s | %s | [`%s`](%s/README.md) |\n' \
    "${name:0:10}" \
    "${topic:-*(missing topic)*}" \
    "${status:-*(missing)*}" \
    "${updated:-—}" \
    "${tags:-—}" \
    "$name" "$name"
done
