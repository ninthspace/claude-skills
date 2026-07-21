#!/usr/bin/env bash
#
# check-spec.sh — structural check for a nova-to-filament emitted spec.
#
# Validates that a spec emitted by the nova-to-filament skill satisfies the
# structural invariants the skill promises, WITHOUT judging migration content:
#
#   1. cpm template conformance — the required section headings are present and
#      the Acceptance Criteria Coverage table exists with a Disposition column.
#   2. Version stamp — the header carries both the Nova and the Filament version.
#   3. No empty disposition cells — every data row of the reconciliation ledger
#      (the AC Coverage table) has a non-empty Disposition cell.
#   4. Taxonomy coverage — every Nova taxonomy category appears in the emitted
#      spec or its coverage manifest.
#
# This is a fast, dependency-free CI/self-check guard. It is NOT the
# reconciliation gate itself (that runs inside the skill, pre-write) — it is a
# structural backstop over the written artifact.
#
# Usage:
#   check-spec.sh <spec.md> [<coverage-manifest.md>]
#
# If the manifest path is omitted it is derived from the spec path by inserting
# "-coverage" before the ".md" extension (the skill's naming convention).
#
# Exit status: 0 if all checks pass; non-zero if any check fails.

set -euo pipefail

# --- argument handling -------------------------------------------------------

if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
  echo "usage: check-spec.sh <spec.md> [<coverage-manifest.md>]" >&2
  exit 2
fi

spec="$1"
if [ "$#" -eq 2 ]; then
  manifest="$2"
else
  manifest="${spec%.md}-coverage.md"
fi

if [ ! -f "$spec" ]; then
  echo "FAIL: spec file not found: $spec" >&2
  exit 2
fi

# The taxonomy search target: prefer the manifest (which carries the census);
# fall back to the spec itself when no manifest is present.
if [ -f "$manifest" ]; then
  taxonomy_target="$manifest"
else
  taxonomy_target="$spec"
fi

fails=0
fail() { echo "FAIL: $*" >&2; fails=$((fails + 1)); }
pass() { echo "ok:   $*"; }

# --- 1. cpm template conformance --------------------------------------------

required_headings=(
  "# Spec:"
  "## Problem Summary"
  "## Functional Requirements"
  "### Must Have"
  "## Testing Strategy"
  "### Tag Vocabulary"
  "### Acceptance Criteria Coverage"
)
for h in "${required_headings[@]}"; do
  if grep -qF -- "$h" "$spec"; then
    pass "heading present: $h"
  else
    fail "missing required heading: $h"
  fi
done

# --- 2. version stamp --------------------------------------------------------

if grep -qF -- "**Nova version**:" "$spec"; then
  pass "Nova version stamped"
else
  fail "header missing Nova version stamp (**Nova version**:)"
fi
if grep -qF -- "**Target Filament version**:" "$spec"; then
  pass "Filament version stamped"
else
  fail "header missing Filament version stamp (**Target Filament version**:)"
fi

# --- 3 & template: AC Coverage table present + no empty disposition cells -----
#
# awk walks the "### Acceptance Criteria Coverage" section, identifies the
# pipe-delimited table, confirms the last column is "Disposition", and checks
# that every data row's Disposition cell is non-empty. It prints machine-
# readable markers the shell interprets below.

ledger_report="$(
  awk -F'|' '
    /^### Acceptance Criteria Coverage/ { in_sec = 1; next }
    in_sec && /^#/ { in_sec = 0 }                  # next heading ends the section
    in_sec && /^\|/ {
      if ($0 ~ /^\|[ :|-]+$/) next                 # markdown separator row
      if (!seen_header) {                          # first pipe row is the header
        seen_header = 1
        ncol = NF
        dispidx = NF - 1                           # last content column (trailing | => empty NF)
        label = $(dispidx); gsub(/^[ \t]+|[ \t]+$/, "", label)
        if (label != "Disposition") print "NO_DISP_COL"
        next
      }
      rows++
      if (NF != ncol) { print "BADCOLS"; next }
      d = $(dispidx); gsub(/^[ \t]+|[ \t]+$/, "", d)
      if (d == "") print "EMPTY"
    }
    END {
      if (!seen_header) print "NO_TABLE"
      print "ROWS " rows + 0
    }
  ' "$spec"
)"

if grep -q "NO_TABLE" <<<"$ledger_report"; then
  fail "no Acceptance Criteria Coverage table found under its heading"
elif grep -q "NO_DISP_COL" <<<"$ledger_report"; then
  fail "AC Coverage table has no trailing 'Disposition' column"
else
  ledger_rows="$(awk '/^ROWS /{print $2}' <<<"$ledger_report")"
  if [ "${ledger_rows:-0}" -eq 0 ]; then
    fail "AC Coverage table has a header but no data rows"
  elif grep -q "BADCOLS" <<<"$ledger_report"; then
    fail "AC Coverage table has a row with the wrong column count"
  elif grep -q "EMPTY" <<<"$ledger_report"; then
    empties="$(grep -c "EMPTY" <<<"$ledger_report")"
    fail "AC Coverage ledger has $empties row(s) with an empty Disposition cell"
  else
    pass "ledger present with $ledger_rows row(s), all dispositions filled"
  fi
fi

# --- 4. taxonomy coverage ----------------------------------------------------
#
# Each entry is "Label:regex". A category is covered if its regex matches the
# taxonomy target (manifest preferred, spec otherwise), case-insensitively.

taxonomy=(
  "Resources:[Rr]esources"
  "Fields:[Ff]ields"
  "Actions:[Aa]ctions"
  "Filters:[Ff]ilters"
  "Lenses:[Ll]enses"
  "Metrics:[Mm]etrics"
  "Dashboards:[Dd]ashboards"
  "Cards:[Cc]ards"
  "Relationships:[Rr]elationships"
  "Policies/Authorization:[Pp]olicies|[Aa]uthorization"
  "Custom Tools:[Cc]ustom [Tt]ools|[Tt]ools"
)
for entry in "${taxonomy[@]}"; do
  label="${entry%%:*}"
  regex="${entry#*:}"
  if grep -Eq -- "$regex" "$taxonomy_target"; then
    pass "taxonomy category present: $label"
  else
    fail "taxonomy category missing from $(basename "$taxonomy_target"): $label"
  fi
done

# --- verdict -----------------------------------------------------------------

echo
if [ "$fails" -eq 0 ]; then
  echo "PASS: $(basename "$spec") satisfies all structural checks."
  exit 0
else
  echo "FAILED: $fails structural check(s) failed for $(basename "$spec")." >&2
  exit 1
fi
