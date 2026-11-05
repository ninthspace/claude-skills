---
name: prune-comments
description: Review every comment in first-party source from the current directory down — excluding vendor/, node_modules/ and other dependency trees — and keep only comments that describe the code as it is now and tell a reader something the code cannot. Deletes history (what changed, what it replaced, who, when, "previously", "now uses", fixed-in ticket refs), restatement of the code, commented-out code, notices aimed at coding agents or harnesses, and leftover conversational artifacts; shortens the comments that stay. Use when asked to "tidy up the comments", "remove unnecessary comments", "strip the history out of the comments", "these comments are too long", "prune the comments", "remove the AI comment noise", "strip agent instructions from the code", or "the docblocks are longer than the methods". Reports for approval by default; add "sweep", "just do it", or "skip the report" to work straight through and review the diff instead.
license: MIT
metadata:
  author: ninthspace
  version: "2.0"
---

# prune-comments

A comment is kept only when it passes both tests:

1. **It describes the code as it is now.** Not what the code used to do, what it
   replaced, who changed it, when, or in which ticket. Git and the issue tracker hold
   that record; a copy in the source goes stale and misleads.
2. **It tells a reader something the code cannot.** Why this order, why this
   exception, what breaks if it changes, which external constraint forces a
   workaround that is still in the code, a contract the type system cannot express.
   What the function is called, what its parameters are named, or that a loop loops
   is already in the code.

A comment that fails either test is deleted, or cut down to the part that passes.
Every comment is in scope, including one-liners, trailing comments after code, and
docblocks — not only long blocks.

Two hard outcomes, enforced in both modes:

- **Every changed comment ends shorter than it started, or is deleted.** Most
  surviving comments fit in one to three lines. A longer one stays only when each line
  passes both tests (reference case 9).
- **Nothing but comments change.** No code edits, no reformatting, no "while I'm
  here" fixes. Removing a trailing comment removes the marker, the comment text and
  the whitespace before it; the code on that line is left byte-for-byte unchanged.

## What counts as history

Delete it, or rewrite the comment in the present tense without it:

- Change narration — "changed from X to Y", "refactored to use…", "was moved here
  from…", "replaced the old implementation", "added for the v2 launch".
- Before/after framing — "previously", "used to", "no longer", "now uses",
  "the new approach", "the old way", "originally". A comment that says "now uses the
  cache" becomes "uses the cache" — or goes, if the code already says so.
- Attribution and dates — `@author`, `@since`, `@version` and `@changed` tags in
  application code, initials, "added by", dated notes such as "2024-03-01: fixed".
  Keep `@since` on a published library's public API, where it documents the API
  rather than the file's history.
- Ticket and PR refs that record origin only — "fixes #123", "see PROJ-456 for
  background", "added in PR 78".
- Removed-code epitaphs — "we used to validate here but that moved to the request".

**References that stay:** a ticket, issue or upstream URL is current state when it
explains a constraint still in the code — "works around laravel/framework#4471;
remove when on 12.x", "limit set by the supplier's API, see ops#88". Keep the ref,
cut any narrative around it. If you cannot tell whether the workaround it explains is
still present, read the code; if you still cannot tell, leave it and list it
(reference case 10).

**TODO / FIXME** describe outstanding work on the current code, not history. Keep
one that names a specific, still-valid problem. Delete one that is vague ("TODO:
clean up"), already done, or refers to code that no longer exists.

## Posture: two modes

**Review mode (default)** — report before editing:

1. Scan and produce a findings report — file:line, disposition, and the proposed
   replacement text in full for every rewrite.
2. Get approval in reviewable batches grouped by directory — never one bulk apply
   across hundreds of comments.
3. Apply with the Edit tool, file by file, so each change diffs cleanly.

**Agent artifacts** (Step 4) are never legitimate content; propose removal by
default rather than flagging for a decision.

### Sweep mode — skip the report and work straight through

When the user asks for it — "just do it", "skip the report", "sweep it", "don't ask,
I'll read the diff", or the skill is invoked with `sweep` — drop the approval gate
and work through every comment in scope in one pass. **git is the review surface**.

What does **not** change in sweep mode:

- Every disposition is still decided against the code, comment by comment, in file
  order. Sweep mode removes the gate, not the judgement.
- Step 5's never-touch list still applies in full, framework scaffold comments
  included.
- The Step 6 verification still runs in full, and its report is the only record of
  what happened, so it carries the per-comment detail.
- Anything ambiguous (reference case 10) is still left alone and listed.

Before starting a sweep, say in one line the scope you resolved and the comment
count. If the working tree is dirty, say so — the diff is the review. If the count is
very large, suggest a narrower scope and proceed unless told otherwise.

Commit nothing. The edits are left in the working tree for the user to review.

## Step 0 — Scope

Default scope is the current working directory downwards. First-party means: not a
dependency, not build output, not generated, not minified. The scanner enforces this.

Every comment is reviewed, so counts run much higher than for long blocks alone.
Confirm scope before scanning a whole monorepo: a scoped run (`app/`, `src/`, a
feature directory, the files changed on this branch) gives a reviewable diff.

For changed files only: `git diff --name-only main...HEAD`.

For a large scope, split it by directory and give each directory to a subagent with
this SKILL.md and `references/comment-judgement.md` as its instructions, in the same
mode. Each subagent reports per-comment rows in the Report format; you merge them and
run Step 6 once over the whole scope.

## Step 1 — Find the comments

```bash
python3 scripts/find_comments.py                    # cwd down, every comment
python3 scripts/find_comments.py app resources      # scoped
python3 scripts/find_comments.py --json             # machine-readable
python3 scripts/find_comments.py --only-flagged     # agent/history/scaffold/commented-code only
python3 scripts/find_comments.py --no-trailing      # whole-line comments only
python3 scripts/find_comments.py --min-lines 4      # long blocks only (no trailing)
```

The scanner walks from the given paths, uses `git ls-files` where it can (so
`.gitignore` is honoured), and hard-skips dependency trees, build output, minified
and generated files. It handles line comments, block comments and trailing comments
across PHP, Blade, JS/TS, Vue, Svelte, CSS/SCSS, Python, Ruby, Go, Rust, Java,
C-family, shell, YAML, SQL, HTML and more.

It is a heuristic. A whole-line comment counts only when the marker starts the line;
a trailing comment counts only when its marker follows whitespace with balanced quotes
before it, and PHP attributes (`#[...]`) are not treated as comments. Expect the odd
false positive in a trailing match inside a multi-line string — check the line before
editing it. The scanner points; you judge against the code the comment sits on, which
means **reading that code**, not just the comment.

Its flags are triage hints, not verdicts:

| Flag | Means | Default |
| --- | --- | --- |
| `history` | change narration, before/after wording, dates, attribution tags, fixed-in refs | usually delete or rewrite in present tense |
| `agent` | mentions an agent, model, or harness | read it — a *mention* is not a *notice* |
| `scaffold` | framework or generator boilerplate wording | usually delete |
| `commented-code` | the comment is disabled code, not prose | propose deletion; git has it |
| `preserve` | licence, SPDX, tool directive, or type-carrying tag | leave alone (Step 5) |

An unflagged comment is not a kept comment. Most restatement carries no flag.

## Step 2 — Decide a disposition per comment

Read the code the comment describes, apply the two tests, then pick one:

- **DELETE** — it restates the code, narrates the obvious, records history, is stale,
  is commented-out code, or is an agent artifact. Deletion is the common outcome.
- **REWRITE** — part of it passes both tests, buried in history, preamble or
  restatement. Keep that part, in the present tense, and cut the rest.
- **KEEP** — it passes both tests as written, or it is on the never-touch list.

## Step 3 — Rewrite discipline

- **Lead with the why.** "Ordered by `sort_key` because the API returns them
  unsorted" beats four lines of context leading to the same fact.
- **Present tense, current state.** "Now reads from the replica" becomes "Reads from
  the replica" — then ask whether the code already says that.
- **Drop restatement.** If a sentence can be derived from the signature, the name,
  or the next two lines of code, it goes.
- **Drop conversational register.** "Note that…", "We need to…", "This function
  simply…", "First, we… Then, we…". Say the thing.
- **Match the file.** If the file writes terse one-liners, don't leave a paragraph.
- **Keep every current-state fact.** Upstream bug URLs, version constraints, removal
  conditions, warnings about ordering or side effects survive verbatim.
- **Never invent.** If a comment is vague and you can't tell whether it holds a real
  fact, leave it and list it. Don't manufacture a rationale.

Structured docblocks need care: `@param` with a type the language can't express,
`@var`, `@throws`, `@template`, `@deprecated`, `@return` on a mixed return — these are
read by IDEs and static analysis. Cut the prose around them; keep the tags. A
docblock whose only content is a summary repeating the method name, or `@param` tags
restating already-typed parameters, goes entirely.

For worked before/after examples across PHP, JS, Python and Blade — including
comments correctly left alone — read `references/comment-judgement.md`.

## Step 4 — Agent notices and artifacts

Remove these by default. They are addressed to a tool, not a reader:

- Instructions to a coding agent or harness — "Claude: don't edit below this line",
  "AI assistant: preserve this structure", "for the model reading this".
- Session residue — "as requested", "as discussed above", "Step 3 of 5", "updated
  per your feedback", "I've refactored this to…", first-person narration of an edit.
- Generation banners on files that are not generated — "generated with Claude Code"
  — where nothing regenerates the file.
- Generator prompts left in filled-in files — "Add your routes here", "You may
  register your bindings below", "Feel free to…".

If a notice wraps a real constraint on the code, keep the constraint and drop the
address (reference case 5). Keep a real `@generated` / `DO NOT EDIT` banner on a
genuinely generated file, a `CLAUDE.md` reference that documents where project
instructions live, and a comment that mentions an AI feature the code implements.

## Step 5 — Never touch

- Licence and copyright headers, SPDX identifiers, attribution notices.
- Tool directives and pragmas: `eslint-disable`, `prettier-ignore`, `phpcs:ignore`,
  `phpstan-ignore`, `@psalm-*`, `# noqa`, `# type: ignore`, `@ts-expect-error`,
  `@ts-check`, `/// <reference>`, `@codeCoverageIgnore`, `istanbul ignore`,
  `//go:build`, `//go:generate`, `#nosec`, `# shellcheck`, encoding and
  `frozen_string_literal` magic comments, `#region`.
- Banners on genuinely generated files (`@generated`, "DO NOT EDIT").
- Doc-comment tags carrying type or contract information (Step 3).
- Comments inside vendored or third-party code that happens to be committed.
- **Framework scaffold comments in `config/`, `bootstrap/`, and default migrations.**
  These are upstream text; editing them makes every framework upgrade diff against
  your changes. Offer them as a separate batch if the user wants them gone.

## Step 6 — Apply and verify

Apply approved changes with the Edit tool, one file at a time. Then:

1. **Comments-only check** — `git diff -U0`. Every removed or added line must be a
   comment line, or a code line whose only difference is a removed trailing comment
   (the new line equals the old line with the comment and the whitespace before it
   cut off). Anything else is a mistake; revert it.
2. **Rescan** — re-run the scanner over the touched paths. Every comment you deleted
   is gone, every one you rewrote is shorter, and nothing new appeared.
3. **Syntax check** — `php -l`, `node --check`, `python -m py_compile`, `go vet`, as
   fits. A broken block-comment terminator, or a trailing-comment cut that took a
   quote with it, is how this skill can break a build.
4. **Formatter and tests** — `vendor/bin/pint --dirty --format agent` if PHP files
   changed; run the project's linter and test suite if the change was large.

Report: comments deleted, comments rewritten (with lines saved), comments kept that
needed a judgement and why, and anything flagged but left for the user to decide.

## Report format

List every delete and rewrite. List a keep only when it was flagged, is four lines or
more, or needed a judgement; give the count of the other keeps per file.

```
### app/Services/BookingService.php
- L14-22 [rewrite · 9→1] docblock restating the signature; kept the retry rationale
- L31 [delete · history] "Switched to the v2 endpoint in March"
- L47 [rewrite · trailing] "// now cached" → removed; Cache::remember on the line says it
- L88-91 [delete] narrates the loop below
- L140-145 [keep] documents the off-by-one in the upstream API; still worked around
- 6 other comments kept

### resources/js/checkout.js
- L3-8 [delete · agent] "Claude: keep this ordering" — addressed to a tool
- L61-70 [delete · commented-code] superseded implementation, in git history
- L112 [leave] "don't move this" — real warning, no recoverable reason; needs a human
```

## Guardrails

- Present tense and current state only. History belongs in git.
- Shorter and truer. A rewrite that loses a current-state fact is a regression.
- Comments-only diffs. Never edit code, never reformat, never fix "while I'm here".
- Read the code before judging the comment. A comment that looks redundant may be the
  only record of why the obvious implementation doesn't work.
- Leave the ambiguous ones alone and say so. Don't guess at intent.
