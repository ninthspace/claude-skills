# Output skeletons

Four reader-appropriate shapes. Propose the one that fits how the reader will use the document; do not force all four. Drop any section that carries no content for this reader — do not pad it.

## User guide

For a reader learning to perform a set of tasks.

```markdown
# [What you can do with this] — a guide for [role]

## Before you start
[Preconditions, access, what you'll need.]

## [Task 1, in the order you do it]
## [Task 2] …

## When something goes wrong
[The common failures and what to do about each.]

## Quick reference
[States, rules, codes — the things looked up mid-task.]

## Glossary
```

## "What changes for you" change note

For staff adopting a new or changed system. Anchors every item to their current process.

```markdown
# [System]: what changes for [team]

## In one line

## Why this is better for you
[Kept short — adoption, not architecture.]

## What changes, step by step
[Today you do X → now you do Y, per task.]

## What stays the same / stays manual
[The honest caveats and open questions.]

## What we're asking of you

## Glossary
```

## Quick reference / cheat sheet

For an experienced operator who needs values and rules at hand, not prose.

```markdown
# [Task] quick reference

## States
[Table: state → what it means → what you do.]

## Rules
[Table: rule → why → what happens if broken.]

## When X, do Y
[Table of conditions and actions.]

## Codes & values
[Exact codes, thresholds, filename patterns, sequence order.]
```

## Runbook / SOP

For a procedure that must be run the same way every time, including on failure.

```markdown
# [Procedure] — standard operating procedure

## Purpose & when to run this

## Preconditions

## Steps
[Numbered; one action each; warning before the step it governs.]

## On failure
[What to do when a step fails.]

## Records
[What to log; audit trail.]
```
