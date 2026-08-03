#!/usr/bin/env bash
#
# install.sh — symlink each shared skill into your personal Claude Code skills dir,
# symlink each shared output style into your personal output-styles dir, and
# activate this repo's tracked git hooks.
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
STYLE_SRC="${REPO}/.claude/output-styles"
STYLE_DEST="${HOME}/.claude/output-styles"

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

# Output styles. Unlike skills, these are NOT picked up by `claude --add-dir` —
# Claude Code reads output styles only from ~/.claude/output-styles/ (or a project
# .claude/output-styles/). Symlinking is therefore the only install path, and a
# skill that selects a style (e.g. `terse`) fails without it.
if [ -d "${STYLE_SRC}" ]; then
  mkdir -p "${STYLE_DEST}"

  styled=0
  for style in "${STYLE_SRC}"/*.md; do
    name="$(basename "${style}")"
    ln -sfn "${style}" "${STYLE_DEST}/${name}"
    echo "linked style: ${name} -> ${STYLE_DEST}/${name}"
    styled=$((styled + 1))
  done

  if [ "${styled}" -eq 0 ]; then
    echo "No output styles found under ${STYLE_SRC} yet."
  else
    echo "Done. ${styled} output style(s) available in ${STYLE_DEST}."
  fi
fi

# Activate tracked git hooks — the pre-commit guard that blocks the
# non-redistributable ASD-STE100 standard from being committed. Harmless to re-run.
if ( cd "${REPO}" && git rev-parse --is-inside-work-tree >/dev/null 2>&1 ); then
  ( cd "${REPO}" && git config core.hooksPath .githooks )
  echo "git hooks: core.hooksPath -> .githooks (pre-commit guard active)"
fi
