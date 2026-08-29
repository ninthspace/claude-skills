---
name: prune-comments
description: Review first-party source from the current directory down — excluding vendor/, node_modules/ and other dependency trees — find every comment block of four lines or more, and shorten it to three lines or fewer, or delete it where it only restates the code. Also strips notices and guidance aimed at coding agents or harnesses, and leftover conversational artifacts. Use when asked to "tidy up the comments", "these comments are too long", "prune the comments", "remove the AI comment noise", "strip agent instructions from the code", or "the docblocks are longer than the methods". Reports for approval by default; add "sweep", "just do it", or "skip the report" to work straight through and review the diff instead.
license: MIT
metadata:
  author: ninthspace
  version: "1.0"
---

# prune-comments

Long comments are usually a symptom, not a service. A four-line block above a
three-line method is almost always restating the signature, narrating a decision
that no longer exists, or addressing a coding agent that has since left the room.
This skill finds those blocks across first-party source and cuts each one to the
sentence that actually earns its place — or removes it.

The bar for keeping a line: **it tells a reader something the code cannot.** Why
this order, why this exception, what breaks if it changes, which ticket or
regression forced the workaround, which upstream bug it works around. Everything
else — what the function is called, what its parameters are named, that a loop
loops — is already in the code, and the comment is a second copy that will rot.

Two hard outcomes, both enforced:

- **Every rewritten block ends strictly shorter than it started.** Target three
  lines or fewer, including delimiters. If a rewrite doesn't fit in three lines,
  either it's carrying real complexity — say so in the report and keep it at four —
  or the comment is doing a job the code should be doing.
- **Nothing but comment lines change.** No code edits, no reformatting, no
  "while I'm here" fixes. The diff is comments only.

## Posture: two modes

**Review mode (default)** — report before editing, in the house style:

1. Scan and produce a findings report — file:line, current length, disposition,
   and the proposed replacement text in full.
2. Get approval, in reviewable batches grouped by directory or category — never one
   bulk apply across hundreds of blocks.
3. Apply with the Edit tool, file by file, so each change diffs cleanly.

The one exception is the **agent-artifact** category (Step 4). Those are never
legitimate content; propose removal by default rather than flagging for a decision.

### Sweep mode — skip the report and work straight through

When the user asks for it — "just do it", "skip the report", "sweep it", "don't ask,
I'll read the diff", or the skill is invoked with `sweep` — drop the approval gate
and work through every block in scope in one pass. **git is the review surface**, and
a comments-only diff is a genuinely easy thing to read.

What changes: no report up front, no per-batch approval. What does **not** change —
these hold in both modes, and sweep mode is not licence to relax them:

- Every disposition is still decided against the code, block by block, in file order.
  Sweep mode removes the gate, not the judgement.
- Step 5's never-touch list still applies in full, framework scaffold comments
  included. Sweep mode does not widen the scope; ask for those explicitly.
- The Step 6 verification still runs, in full, and its report is now the *only*
  record of what happened — so it carries the per-block detail the up-front report
  would have: what was deleted, what was shortened, what was kept and why.
- Anything genuinely ambiguous (reference case 10 — a real warning with no
  recoverable fact) is still left alone and still listed. Sweep mode means "don't ask
  before each change", not "guess when unsure".

Two things to say before starting a sweep, in one line, not a gate: the scope you
resolved and the block count. If the working tree is dirty, mention it — the diff is
the review, so it wants to be a clean one. If the count is very large (several
hundred blocks across an unscoped monorepo), suggest a scope and proceed unless told
otherwise; a 600-block diff is technically reviewable and practically not.

Commit nothing. The edits are left in the working tree for the user to review.

## Step 0 — Scope

Default scope is the current working directory downwards. First-party means: not a
dependency, not build output, not generated, not minified. The scanner enforces
this (see below), but confirm what you're about to touch before scanning a whole
monorepo — a scoped run (`app/`, `src/`, a feature directory, the files changed on
this branch) produces a reviewable diff; an app-wide sweep of 500 blocks does not.

For changed files only: `git diff --name-only main...HEAD`.

## Step 1 — Find the blocks

```bash
python3 scripts/find_comments.py                    # cwd down, blocks of 4+ lines
python3 scripts/find_comments.py app resources      # scoped
python3 scripts/find_comments.py --json             # machine-readable
python3 scripts/find_comments.py --only-flagged     # agent/scaffold/commented-code only
```

The scanner walks from the given paths, uses `git ls-files` where it can (so
`.gitignore` is honoured for free), and hard-skips dependency trees, build output,
minified files and generated files regardless. It handles line comments and block
comments across PHP, Blade, JS/TS, Vue, Svelte, CSS/SCSS, Python, Ruby, Go, Rust,
Java, C-family, shell, YAML, SQL, HTML and more.

It is a heuristic, and deliberately conservative: a line counts as a comment only
when the marker starts the line, so trailing comments and markers inside strings are
never reported. It points; you judge — against the code the comment sits on, which
means **reading that code**, not just the block.

Its flags are triage hints, not verdicts:

| Flag | Means | Default |
| --- | --- | --- |
| `agent` | mentions an agent, model, or harness | read it — a *mention* is not a *notice* |
| `scaffold` | framework or generator boilerplate wording | usually delete |
| `commented-code` | the block is disabled code, not prose | propose deletion; git has it |
| `preserve` | licence, SPDX, or a tool directive | leave alone (Step 5) |

## Step 2 — Decide a disposition per block

Read the code the comment describes, then pick one:

- **DELETE** — it restates the code, narrates the obvious, is stale, is commented-out
  code, or is an agent artifact. Deleting is a legitimate and common outcome; a
  comment that adds nothing is worse than no comment, because it invites trust.
- **REWRITE** — there's a real fact in there, buried in preamble. Extract it and cut
  the rest. This is the usual case for a long docblock: one line of substance
  wrapped in four lines of ceremony.
- **KEEP** — genuinely load-bearing and genuinely needs the length (a non-obvious
  algorithm, a documented invariant, a legal or licence header, a structured docblock
  carrying type information). Record why in the report, and move on.

## Step 3 — Rewrite discipline

Cut to the substance, phrased for the thing being commented:

- **Lead with the why.** "Ordered by `sort_key` because the API returns them
  unsorted" beats four lines of context leading to the same fact.
- **Drop restatement.** If a sentence can be derived from the signature, the name,
  or the next two lines of code, it goes.
- **Drop conversational register.** "Note that…", "We need to…", "This function
  simply…", "First, we… Then, we…", "As you can see". Say the thing.
- **Keep the vocabulary of the domain and the file.** Match the surrounding comment
  style — if the file writes terse one-liners, don't leave a paragraph.
- **Preserve every fact.** Ticket refs, issue numbers, upstream bug URLs, dates,
  version constraints, named workarounds, warnings about ordering or side effects —
  these survive verbatim or the comment isn't shorter, it's lossier.
- **Never invent.** If the long comment is vague and you can't tell whether it holds
  a real fact, say so in the report and leave it. Don't manufacture a rationale.

Structured docblocks need care: `@param` with a type PHP can't express, `@var`,
`@throws`, `@template`, `@deprecated`, `@return` on a mixed return — these are read
by IDEs and static analysis. Cut the prose around them; keep the tags. A docblock
whose *only* content is `@param` tags restating already-typed parameters is pure
noise and can go entirely.

For **language reference and worked before/after examples across PHP, JS, Python and
Blade — including three blocks correctly left alone — read
`references/comment-judgement.md`.**

## Step 4 — Agent notices and artifacts

Remove these by default. They are addressed to a tool, not a reader, and they are
noise in a codebase that outlives the session that produced them:

- Instructions to a coding agent or harness — "Claude: don't edit below this line",
  "AI assistant: preserve this structure", "cursor: ignore", "for the model reading
  this", pseudo-directives that no tool actually honours.
- Session residue — "as requested", "as discussed above", "Step 3 of 5", "updated
  per your feedback", "I've refactored this to…", first-person narration of an edit.
- Generation banners on files that are *not* generated — "this file was created by
  an AI assistant", "generated with Claude Code" — where nothing regenerates the file.
- Scaffolding prompts left from a generator — "Add your routes here", "You may
  register your bindings below", "Feel free to…" — in files that have long since
  been filled in.

Distinguish these from things that look similar and must stay: a real
`@generated` / `DO NOT EDIT` banner on a genuinely generated file, a `CLAUDE.md`
reference that documents where project instructions live, and a comment that merely
*mentions* an AI feature the code implements. The scanner's `agent` flag fires on
all of these; read before you cut.

## Step 5 — Never touch

- Licence and copyright headers, SPDX identifiers, attribution notices.
- Tool directives: `eslint-disable`, `prettier-ignore`, `phpcs:ignore`,
  `phpstan-ignore`, `@psalm-*`, `# noqa`, `# type: ignore`, `@ts-expect-error`,
  `@codeCoverageIgnore`, `//go:generate`, `#nosec`.
- Banners on genuinely generated files (`@generated`, "DO NOT EDIT").
- Doc-comment tags carrying type or contract information (Step 3).
- Comments inside vendored or third-party code that happens to be committed.
- **Framework scaffold comments in `config/`, `bootstrap/`, and default migrations.**
  These are upstream text; rewriting them means every future framework upgrade
  diffs against your edits. Offer them as an explicit, separate batch if the user
  wants them gone — never fold them into a general sweep.

## Step 6 — Apply and verify

Apply approved blocks with the Edit tool, one file at a time. Then:

1. **Comments-only check** — `git diff -U0` and confirm every changed line is a
   comment line. Any code line in the diff is a mistake; revert it.
2. **Shorter check** — re-run the scanner over the touched paths. Every block you
   rewrote is gone from the results or shorter than it was; nothing new appeared.
3. **Syntax check** — the cheap one for the language: `php -l`, `node --check`,
   `python -m py_compile`, `go vet`. A mangled block-comment terminator is the one
   way this skill can break a build.
4. **Formatter and tests** — `vendor/bin/pint --dirty --format agent` if PHP files
   changed; run the project's linter and test suite if the sweep was large.

Report: blocks removed, blocks shortened (with total lines saved), blocks kept and
why, and anything you flagged but deliberately left for the user to decide. After a
sweep this is the only account of the run, so give it per-block — the same rows the
up-front report would have carried, in the same format.

## Report format

```
### app/Services/BookingService.php
- L14-22 [rewrite · 9→2] docblock restating the signature; kept the retry rationale
- L88-91 [delete] narrates the loop below
- L140-145 [keep] documents the off-by-one in the upstream API — needs the length

### resources/js/checkout.js
- L3-8 [delete · agent] "Claude: keep this ordering" — addressed to a tool
- L61-70 [delete · commented-code] superseded implementation, in git history
```

## Guardrails

- Shorter *and* truer. A rewrite that loses a fact is a regression, not a win.
- Comments-only diffs. Never edit code, never reformat, never fix "while I'm here".
- Read the code before judging the comment. A block that looks redundant may be the
  only record of why the obvious implementation doesn't work.
- Deletion is a valid outcome, and often the right one.
- Leave the ambiguous ones alone and say so. Don't guess at intent, don't invent a
  rationale to justify keeping a line.
