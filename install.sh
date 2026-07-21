#!/usr/bin/env bash
#
# install.sh — symlink each shared skill into your personal Claude Code skills dir,
# and activate this repo's tracked git hooks.
#
# Run after cloning, and re-run is harmless (symlinks are refreshed in place).
# `git pull` keeps the linked skills current with no further action.
#
# If symlink-based discovery misbehaves, swap `ln -sfn` for `cp -R` below and
# re-run this script after each pull.

set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="${REPO}/.claude/skills"
DEST="${HOME}/.claude/skills"

mkdir -p "${DEST}"

shopt -s nullglob
linked=0
for skill in "${SRC}"/*/; do
  name="$(basename "${skill}")"
  ln -sfn "${skill%/}" "${DEST}/${name}"
  echo "linked: ${name} -> ${DEST}/${name}"
  linked=$((linked + 1))
done

if [ "${linked}" -eq 0 ]; then
  echo "No skills found under ${SRC} yet."
else
  echo "Done. ${linked} skill(s) available in ${DEST}."
fi

# Activate tracked git hooks — the pre-commit guard that blocks the
# non-redistributable ASD-STE100 standard from being committed. Harmless to re-run.
if ( cd "${REPO}" && git rev-parse --is-inside-work-tree >/dev/null 2>&1 ); then
  ( cd "${REPO}" && git config core.hooksPath .githooks )
  echo "git hooks: core.hooksPath -> .githooks (pre-commit guard active)"
fi
