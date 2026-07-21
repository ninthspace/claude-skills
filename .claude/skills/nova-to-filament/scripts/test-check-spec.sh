#!/usr/bin/env bash
#
# test-check-spec.sh — tests for check-spec.sh.
#
# Runs the structural checker against a known-good and a known-bad fixture and
# asserts the expected exit status of each: the good spec must pass (exit 0) and
# the bad spec must be rejected (non-zero). Exits 0 only if both assertions hold.
#
# Usage: ./test-check-spec.sh

set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
checker="$here/check-spec.sh"
fixtures="$here/fixtures"

failures=0

# Good fixture: expect exit 0. Its coverage manifest is auto-derived.
if "$checker" "$fixtures/good-spec.md" >/dev/null 2>&1; then
  echo "PASS: good-spec.md accepted (exit 0)"
else
  echo "FAIL: good-spec.md was rejected but should pass" >&2
  failures=$((failures + 1))
fi

# Bad fixture: expect non-zero (missing version stamp + empty disposition cell).
if "$checker" "$fixtures/bad-spec.md" >/dev/null 2>&1; then
  echo "FAIL: bad-spec.md was accepted but should be rejected" >&2
  failures=$((failures + 1))
else
  echo "PASS: bad-spec.md rejected (non-zero)"
fi

echo
if [ "$failures" -eq 0 ]; then
  echo "ALL TESTS PASSED"
  exit 0
else
  echo "$failures TEST(S) FAILED" >&2
  exit 1
fi
