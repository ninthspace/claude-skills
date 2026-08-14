---
name: explain-skill
description: User-invoked only — run when the user types "/explain-skill", never on your own initiative and never from a subagent. Produces a one-page artifact explaining what a named skill or plugin is, what it does, how it is invoked, what arguments and options it takes, how it behaves step by step, and what it produces — with a worked example input and a rendered or embedded example output. Accepts several targets at once; with two or more, the page gains a navigation bar in its header. Do not use to write or edit a skill (that is ordinary work), to explain a whole codebase (code-to-uml), or to document a product for end users (operator-rewrite).
license: MIT
disable-model-invocation: true
argument-hint: <skill-or-plugin> [more skills or plugins…]
metadata:
  author: ninthspace
  version: "1.0"
---

# Explain Skill

Turn one or more installed skills or plugins into a **single one-page artifact** that a reader can use to decide whether to reach for the thing, and then use it correctly: what it is, when it applies, how it is invoked, every argument and option it really accepts, how it behaves, and what it leaves behind — closing with a worked example showing input and output.

The audience is someone who has the skill installed and still doesn't know what typing it will do. That makes **the example pair the centre of the page**, not an appendix: a reader learns more from one honest input/output pair than from a paraphrase of the instructions.

## Step 0 — main session only; refuse to run as a subagent

**If you are a subagent, stop here.** Return what you were asked to look up and let the main session run this skill.

The frontmatter carries `disable-model-invocation: true`, so the harness admits only a typed `/explain-skill`. Treat this step as the backstop for setups where that field isn't honoured, and don't route around it — no dispatching a subagent to build the page on your behalf. Reading files for breadth is fine and often necessary (see Step 2); *building and publishing the artifact* is the main session's job, because the page is published under the user's account and the closing confirmation is a conversation with them. The same doctrine as [`show-me`](../show-me/SKILL.md) Step 0.

## Step 1 — Resolve every named target

The argument is a list, in any of the forms a user actually types: `laravel-dtos`, `/cpm:do`, `cpm` (the whole plugin), `code-review --fix` (strip the flag, explain the skill). Resolve each independently and keep the user's spelling for display.

Search in this order and stop at the first hit, noting **where** it was found — provenance goes on the page:

| Kind | Look in |
| --- | --- |
| Project skill | `.claude/skills/<name>/SKILL.md` |
| Personal skill | `~/.claude/skills/<name>/SKILL.md` (often a symlink — follow it to the real repo) |
| Plugin skill (`plugin:skill`) | `~/.claude/plugins/cache/<marketplace>/<plugin>/<version>/skills/<name>/SKILL.md` |
| Plugin (whole) | that plugin's `.claude-plugin/plugin.json`, plus its `skills/`, `commands/`, `agents/`, `hooks/hooks.json` |
| Slash command | `.claude/commands/<name>.md`, `~/.claude/commands/<name>.md`, or a plugin's `commands/` |
| Marketplace checkout | `~/.claude/plugins/marketplaces/<marketplace>/` when the cache copy is thin |

Two rules on failure, because both failure modes are worse than saying "not found":

- **A target you cannot locate on disk does not get invented.** Some skills are supplied by the harness itself and have no file (the artifact and design skills, for instance). Explain those *only* from their live listing description, label the section **"described from its listing, no file on disk"**, and drop the sections you cannot substantiate — arguments, behaviour steps, outputs.
- **An ambiguous name gets asked about, once.** Where `foo` exists as both a project skill and a plugin skill, name both and let the user pick, rather than silently explaining the one you found first.

If the user named a plugin with many skills, do not attempt deep sections for all of them: give the plugin an inventory section (one line per skill/command/agent, plus its hooks and shared conventions) and offer to expand any of them into a full section.

## Step 2 — Read the source, never recall it

Read the whole `SKILL.md` — plus its `references/`, `assets/`, `scripts/`, and any hook entrypoints. For breadth across a large plugin, dispatch `Explore` agents per subtree and have them report paths and verbatim frontmatter rather than summaries. Extract:

- **Identity** — `name`, the `description` verbatim (it is the trigger contract), version and author from `metadata`.
- **Invocation surface** — `disable-model-invocation` / `user-invocable` (who may call it: user, model, both), `argument-hint`, `allowed-tools`, `context: fork` / `agent`, `model`. These are the badges at the top of a section.
- **Arguments and options** — only invocation forms the file actually documents: `$ARGUMENTS` / `$1` placeholders, named modes and subcommands (`/cpm:retro learn`, `/cpm:retro retire`), flags (`--fix`, `--comment`), and effort or level words the body defines.
- **Behaviour** — the step sequence in order; every **gate** where it stops and asks the user; every **refusal or guard** (a Step 0, a hard stop, a precondition); what it does when something is missing (its degradation paths).
- **Outputs** — what it writes and *where*, with real path patterns (`docs/specifications/{nn}-spec-{slug}.md`), whether it publishes an artifact, and whether it edits files in place.
- **Dependencies** — other skills it loads, sibling skills it routes to, tools or plugins it needs, install steps it assumes.

**Never invent a flag, a mode, or a step.** A fabricated `--dry-run` on a page that looks authoritative is the single worst thing this skill can ship: the reader will type it. If the file leaves an option's behaviour genuinely unclear, say so in the row rather than resolving it by guess.

## Step 3 — Build the example pair

Every section needs one input and the output it produces. Prefer **real over illustrative**:

1. **Look for genuine output first.** A skill that writes to `docs/` often has products sitting on disk from earlier runs, and this repo's own artefacts count. Excerpt one and cite its path — a real example is worth several invented ones.
2. **Otherwise construct one, and label it.** Mark it **illustrative** in the page, not in a footnote. Keep it small, concrete, and in the domain the skill actually serves; a plausible fake beats a generic one, but an unlabelled fake is a defect.

Show the input as the user would type it (`/explain-skill laravel-dtos cpm:do`), plus any prompt context needed for it to make sense.

For the output, match the medium the skill really produces:

- **A file or spec** — a small file tree showing what lands where, plus a trimmed excerpt with an explicit elision marker.
- **A conversation** — a short transcript block, including the gate the skill puts to the user.
- **In-place edits** — a before/after pair, tight enough to read at a glance.
- **An artifact** — this is the case worth getting right, and the reader most needs to see it. **Embed a facsimile of the page, rendered in the artifact**, inside a framed preview panel captioned as a preview: a bordered "browser chrome" container holding a scaled-down, scoped rendering of the real thing's structure — its header, its key panels, one representative diagram. Build it as **scoped markup inside the container** (prefix its classes, keep its styles namespaced), never as a nested `<html>` document, and do not rely on an `iframe` — the artifact CSP is strict, and a blocked frame renders as an empty box. Keep it structurally honest: the point is "this is the shape of what you'll get", so a simplified panel is fine and a misleading one is not.

## Step 4 — Assemble the one page

**Load `artifact-design` before writing HTML**, and `artifact-diagramming` for the diagrams. **Hand-authored inline SVG only — no Mermaid**, matching [`code-to-uml`](../code-to-uml/SKILL.md) and `show-me`: layout control is the whole point when drawing a step sequence with gates in it.

Per target, one section, in this order:

1. **Name, one-line purpose, and badges** — who can invoke it, where it was found, its version, whether it publishes or writes files.
2. **When to use it / when not** — the routing question answered plainly, naming the sibling that owns each excluded case.
3. **Invocation and options** — a table: form, what it does, default. Bare invocation first.
4. **How it behaves** — an inline-SVG flow of the step sequence, with **gates marked distinctly** from automatic steps, and refusals shown as terminal nodes. This is where a diagram earns its place: prose flattens the branch structure that decides whether the reader gets asked anything.
5. **Example** — the input, then the output preview from Step 3.
6. **What it leaves behind** — files and paths, artifacts published, what it never touches.
7. **Source** — the path the section was read from, so a reader can go to the file.

**Navigation bar: only when two or more targets resolved.** Put it in the header area, sticky, one anchor link per target, with the current section marked as the reader scrolls. It stays a single page — anchors, not separate pages. With one target, ship no nav bar at all; a nav list of one is noise.

Keep the whole thing proportionate to what was asked. Three skills is a compact page each, not three essays.

## Step 5 — Publish and confirm

Publish with the `Artifact` tool: a stable short noun-phrase `<title>` naming the target(s), a favicon held stable across redeploys, and a one-sentence `description`. Re-publishing an edit to the same file path redeploys to the same URL.

Then, in the conversation and briefly, report the things a reader of the page cannot check for themselves:

- targets that resolved, and where from;
- targets that did **not** resolve, or that had no file and were described from their listing;
- which examples are real (with paths) and which are illustrative;
- anything the source file left ambiguous that you declined to resolve.

Offer to expand a plugin's inventory into full sections, or to add a target the user forgot.

## Degradation

- **No `Artifact` tool** — produce the identical self-contained HTML and write it to a file, then give the path.
- **No `artifact-design` / `artifact-diagramming`** — apply the principles directly: theme-aware tokens for light and dark, explicit `body` background, wide tables and diagrams scrolling in their own `overflow-x: auto` container, nothing loaded from outside.
- **Harness-supplied skill with no file** — listing description only, labelled as such, missing sections dropped rather than filled.
- **A target that is a private or client-specific skill** — the page reproduces its content; if the skill body is sensitive, write the file locally and skip publishing, saying why once.

## Guardrails

- **The user invokes this, never you.** Typed `/explain-skill` only — not on your own initiative, not from a subagent, not by dispatching one.
- **Read the file; never explain from memory.** A skill you "know" may have been rewritten this morning.
- **No invented arguments, flags, modes, or steps.** Documented forms only; unclear stays marked unclear.
- **Label every illustrative example.** Real examples carry their path; invented ones carry the word.
- **Embedded artifact previews are facsimiles, and say so.** Scoped markup in a framed container, no nested documents, no iframes, nothing external.
- **Nav bar only for two or more targets.** Never a list of one.
- **One page, one artifact.** Several targets share the page; they do not each get a link.
- **Don't re-explain in the chat.** The reply is the link plus the Step 5 report — the page carries the detail.
