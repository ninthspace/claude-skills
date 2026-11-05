#!/usr/bin/env bash
#
# Runs the skill's suite against the stub `cloud` binary. Optional argument: a substring of
# a test name, to run only the tests whose name contains it. Set AGNOSTIC_TERMS to a terms
# file to run the agnostic scan with it.
set -euo pipefail

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

exec php "${SKILL_DIR}/tests/run.php" "$@"
