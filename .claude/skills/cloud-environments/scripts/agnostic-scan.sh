#!/usr/bin/env bash
#
# Usage: agnostic-scan.sh TERMS_FILE [DIR]
#
# Fails when any file under DIR (default: this skill) contains a term from TERMS_FILE, one
# per line (blank lines and lines starting with # are ignored), matched case-insensitively,
# or a Cloud ID of the form org-/app-/env- followed by eight hex digits and a hyphen.
# Prints every file scanned, then each hit.
set -euo pipefail

terms_file="${1:?usage: agnostic-scan.sh TERMS_FILE [DIR]}"
dir="${2:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

[ -f "$terms_file" ] || { echo "terms file not found: $terms_file" >&2; exit 2; }

terms="$(mktemp)"
trap 'rm -f "$terms"' EXIT
grep -v -e '^[[:space:]]*$' -e '^[[:space:]]*#' "$terms_file" > "$terms" || true

hits=0
count=0
while IFS= read -r file; do
  count=$((count + 1))
  echo "scanned ${file#"$dir"/}"
  if [ -s "$terms" ] && grep -n -i -F -f "$terms" "$file" | sed "s|^|FLAG term ${file#"$dir"/}:|" | grep .; then
    hits=1
  fi
  if grep -n -i -E '(org|app|env)-[0-9a-f]{8}-' "$file" | sed "s|^|FLAG cloud-id ${file#"$dir"/}:|" | grep .; then
    hits=1
  fi
done < <(find "$dir" -type f -not -path '*/.git/*' | sort)

echo "scanned ${count} files"
exit "$hits"
