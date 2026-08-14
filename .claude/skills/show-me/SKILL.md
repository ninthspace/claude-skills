---
name: show-me
description: User-invoked only — run when the user types "/show-me", never on your own initiative and never from a subagent. Explains the most recently posed question, position, or statement in the conversation as a visual artifact — plain prose plus diagrams that make it easy to understand — then carries any outcomes, forks, or options the artifact presents back into the session as a choice for the user. Do not use for producing UML from real code across repositories (that is code-to-uml), for rewriting a whole document for an audience (stakeholder-rewrite / operator-rewrite), or for building product mockups (brief-to-mockups).
license: MIT
disable-model-invocation: true
metadata:
  author: ninthspace
  version: "1.1"
---

# Show Me

Take the **last thing put on the table** — a question you asked, a position you took, a recommendation you made, a statement the user pasted in — and turn it into a **single self-contained artifact** that makes it genuinely easy to understand: short prose, at least one diagram, and the trade-offs laid out honestly. Then bring every fork the artifact presents **back into the conversation** as a real choice.

The point is comprehension, not decoration. The user asked for this because words alone didn't land, so a wall of prose with a decorative graphic on top is a failure even if it is accurate.

## The two halves

1. **Explain outward** — an artifact the user (and anyone they later share it with) can read cold and understand.
2. **Carry back inward** — the options, outcomes, and next-step forks named *in that artifact* are re-presented in the session via `AskUserQuestion`, so understanding converts into a decision without the user re-typing anything.

Half two is what distinguishes this skill. An artifact that ends in "so, which way?" and then leaves the user to answer in free text has done half the job.

## Step 0 — main session only; refuse to run as a subagent

**If you are a subagent, stop here.** Do not build the artifact. Return the subject you were given (and anything you have already found out about it) and let the main session run this skill.

This is not ceremony. The skill's second half is a decision put to the user with `AskUserQuestion` — a subagent has no channel to the user, so a subagent run produces an artifact whose forks land nowhere, and the user gets a link with no way to answer it. The subject also *is* the conversation: "the last thing posed" is only visible in the session where it was posed, and a subagent has none of that context.

The frontmatter carries `disable-model-invocation: true`, so the harness allows only a typed `/show-me`. Treat this step as the backstop for setups where that field isn't honoured, and don't route around it — no dispatching a subagent to build the artifact on your behalf. Reaching for this skill unprompted, whether directly or through an agent, is out of bounds even when a diagram would obviously help; suggest that the user run `/show-me` instead.

## Step 1 — Identify the subject, and say what it is

Resolve "the most recently posed question, position or statement" in this order, stopping at the first match:

1. A **question or gate you just put to the user** (including an `AskUserQuestion` they didn't answer) — they want it explained before choosing.
2. A **position, recommendation, or trade-off you just stated** — the "here's what I'd do and why" they didn't fully follow.
3. **Content the user just pasted or referenced** — a spec paragraph, an error, a policy, someone else's argument.
4. The **user's own last question**, where your answer was the thing that didn't land.

Any argument to the skill overrides this resolution (`/show-me the caching trade-off` means that, not the last turn). Open your reply with one line naming the subject in your own words — that single line is the cheap correction seam, and it costs the user a word to redirect. Ask a clarifying question only when two readings would produce genuinely different artifacts.

## Step 2 — Ground it before you draw it

The subject decides how much verification is owed:

- **Grounded in this repo or codebase** — read the real code, files, or artefacts before asserting anything. Every named class, route, table, file, or figure in the artifact must be one you have actually seen. Dispatch `Explore` agents for breadth if the subject spans subsystems. This is the same rule `code-to-uml` runs on: a picture is a claim, and an invented claim is worse than no picture.
- **A conceptual or strategic position** — no code to check, but the *shape* still needs to be true: name the real constraints, the real costs, and the real alternatives you considered rather than a tidied-up version.
- **External content** — represent it faithfully. If you disagree with a pasted argument, the artifact explains it accurately first and marks your objection as *your* objection.

Mark anything uncertain as **inferred** in the artifact rather than smoothing it into confident prose. If the subject is your own recommendation, the artifact must carry the counter-case with real weight — this is an explainer, not a pitch deck for your preferred option.

## Step 3 — Choose the visual that carries the meaning

Pick the diagram from what is actually confusing, not from habit:

| What's hard to grasp | Show it as |
| --- | --- |
| A sequence of events or a process | flow / activity diagram, left-to-right or top-down |
| Who talks to whom, in what order | sequence diagram with lifelines |
| Two or more options being weighed | side-by-side comparison, one column per option, same rows |
| A branch point with consequences | decision tree — fork, then what follows down each limb |
| How parts fit together | component / boundary diagram |
| Before vs after a change | paired diagram, identical layout, differences highlighted |
| Amounts, spread, or trend | a chart — load the `dataviz` skill first |
| A rule with many cases | a table of cases, not prose |

One diagram doing one job beats a single crowded picture. Two or three small diagrams is usually the right answer; six is a sign the subject wasn't scoped.

**Load `artifact-design` before writing any HTML**, and `artifact-diagramming` for the diagram mechanics — including the inline-SVG technique and both-theme legibility.

**Hand-authored inline SVG only — no Mermaid.** Artifacts do render Mermaid natively, but this skill doesn't use it, matching [`code-to-uml`](../code-to-uml/SKILL.md). Mermaid's auto-layout decides what the picture emphasises, and emphasis is the entire job here; hand-authored SVG gives control over spacing, hierarchy, and both themes, and never surprises you with a re-flow. Every other renderer (PlantUML, kroki, anything pulling JS from a CDN) is blocked outright by the artifact CSP and renders nothing. For charts, follow `dataviz` and draw them as inline SVG too.

## Step 4 — Write the artifact

Structure it so a reader who skims the headings still gets the answer:

- **The thing in one sentence** at the top — the question being answered or the position being taken, in plain language, before any detail.
- **The diagram early**, not appended at the end.
- **Short prose around the diagram** — explain what it shows and what follows from it. Assume domain intelligence, not context: the reader may know their business perfectly and this thread not at all.
- **The trade-offs, fairly** — what each option costs, what it forecloses, who it suits.
- **What happens next** — the outcomes and forks, stated explicitly. This section is the source for Step 5, so it must be complete and unambiguous.
- **What this doesn't cover** — one honest line, where it applies.

Keep it proportionate. Explaining one decision fork is a compact page, not a report. Publish with a stable short noun-phrase `<title>`, a favicon, and a one-sentence `description`. Re-publishing an edit to the same file path redeploys to the same URL — reuse it rather than minting a second link.

## Step 5 — Carry the forks back into the session

Once the artifact is published, re-present its decision points in the conversation with `AskUserQuestion`. Rules:

- **The gate and the artifact must agree.** Options come *from* the artifact, with the same labels and the same meaning. Do not silently offer fewer options than the artifact names, and do not introduce one the artifact never discussed.
- **More than four options** — `AskUserQuestion` allows two to four per question, so group them into a first-cut question and a follow-up, or ask the coarse question first. Say in the reply what you grouped and why, so a hidden option isn't lost.
- **Several independent decisions** — up to four questions in one call, one per decision. Never fold two unrelated choices into one option list.
- **Use `preview`** when the options are concrete artefacts to compare — snippets, layouts, config shapes — so the comparison sits beside the choice.
- **Lead with your recommendation** where you have one: first option, `(Recommended)` appended, and the reason in its `description`.
- **Record the answer**, in one line, and then act on it or hand off to the skill that owns the next step. Update the artifact only if the decision changes what it says.

**If the artifact presents no genuine fork, do not manufacture one.** A pure explanation ends with the link and a sentence — an invented decision wastes a gate and teaches the user to distrust them.

A timeout or an unanswered gate is **not** an answer and not approval. Leave the decision open, do any non-blocking work, and re-ask when the user is back.

## Degradation

- **No `Artifact` tool** — produce the identical self-contained HTML, write it to a file, and give the user the path. The carry-back step is unchanged.
- **No `artifact-design` / `artifact-diagramming` skill** — apply the principles directly rather than skipping design: theme-aware tokens for light and dark, explicit `body` background, wide diagrams scrolling in their own `overflow-x: auto` container, no external assets.
- **Sensitive subject matter** — if the subject is something the user framed as private, or the artifact would contain credentials or personal data, write the file and skip publishing. Say why once.

## Guardrails

- **The user invokes this, never you.** Typed `/show-me` only — not on your own initiative, not from a subagent, not by dispatching one.
- **Comprehension is the deliverable.** If the artifact is not easier to understand than the sentence it replaced, it has failed — shorten the prose, not the diagram.
- **Never invent a fact to make a picture tidy.** Verified names only; mark inferred nodes.
- **Hand-authored inline SVG for every diagram.** No Mermaid, no PlantUML, no CDN renderer.
- **Explain, don't sell.** Present the position you hold *and* its counter-case; the user is choosing, not being closed.
- **The artifact restates, it does not decide.** No option is taken, no file changed, no command run, on the strength of the artifact alone.
- **One artifact per subject.** Don't split one question across two links, and don't bundle two unrelated subjects into one page.
- **Don't re-explain in the chat.** The reply is the subject line, the link, and the gate. The artifact carries the detail.
