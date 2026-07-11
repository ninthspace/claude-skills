#!/usr/bin/env bash
#
# install.sh — symlink each shared skill into your personal Claude Code skills dir.
#
# Run after cloning, and re-run is harmless (symlinks are refreshed in place).
# `git pull` keeps the linked skills current with no further action.
#
# If symlink-based discovery misbehaves, swap `ln -sfn` for `cp -R` below and
# re-run this script after each pull.

set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.claude/skills" && pwd)"
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
