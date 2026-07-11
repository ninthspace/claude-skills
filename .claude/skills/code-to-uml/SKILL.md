---
name: code-to-uml
description: Produce a self-contained HTML artifact of UML activity and sequence diagrams that explain how something moves through a codebase — grounded in the real code, spanning one or more repositories. Use when asked to "diagram how X is stored/processed/surfaced", "produce UML activity and sequence diagrams", "draw the sequence diagram for this flow", "visualise the lifecycle of X", "create an architecture artifact showing how Y works across repos", or "chart the request/data flow".
license: MIT
metadata:
  author: ninthspace
  version: "1.0"
---

# Code to UML

Produce a **single self-contained HTML artifact** — publishable with the Artifact tool — containing hand-authored SVG **UML activity and sequence diagrams** (plus an optional system-context panel and data-model table) that explain how a chosen subject moves through the code: how it is collected, stored, processed, and surfaced. The subject might be a comment, an order, an inbound webhook, a queued job, an auth request — anything with a journey through one or more repositories.

The diagrams are drawn from the **actual code**, not a plausible-looking guess. That is the whole value: an architecture picture a reader can trust because every box, arrow, and branch traces back to a real class, route, job, or table.

## Posture: two non-negotiables

**1. Accuracy is the product.** Every node, lifeline, message, decision, and table must map to code you have actually read — real `ClassName`, `route`, `Job`, `method()`, column names, real branches. Explore first, draw second. A smaller diagram that is *true* beats a complete one that is invented. Where the code is genuinely ambiguous, mark the node **inferred** rather than asserting flow that isn't there.

**2. Self-contained SVG — never Mermaid.** The Artifact runtime enforces a strict Content-Security-Policy: no external scripts, no CDN requests, no remote fonts. Mermaid, PlantUML, kroki, and every other diagram-renderer that loads JS from a CDN are therefore **unavailable** — they render nothing. Hand-author inline SVG instead. This is a feature, not a limitation: it gives full control over layout, theming, and legibility, and it always works.

## Native dependencies & degradation

This skill builds on two capabilities of the **running Claude Code environment**, not this repo: the native **`Artifact` tool** (publishing) and the native **`artifact-design` skill** (design calibration). Neither is guaranteed to be present on every teammate's setup, so degrade rather than dead-end:

- **No `Artifact` tool** — still produce the exact same self-contained HTML, and write it to a file for the user to open in a browser. Only the final publish step changes; the deliverable does not.
- **No `artifact-design` skill** — apply its principles directly instead of skipping design. The bundled `assets/artifact-scaffold.html` already encodes the essentials (the token system, the light/dark theming contract, a mono-forward technical treatment), so a clean result is reachable without it.

When both are present, use them — they are the intended substrate.

## Step 1 — Scope the request

Pin these before touching code:

- **Subject** — the single thing being traced (the token that flows). Everything in the diagrams is *this thing's* journey. If the prompt names several, pick the primary one or confirm.
- **Repositories** — one or several. If more than one, identify the **seam(s)** between them (an HTTP call, a queue, a shared table). Seams become the boundaries drawn in the diagrams.
- **Phases** — what the prompt asks to cover ("stored, processed, surfaced"; "request → response"; "ingest → analyse"). These set the diagram's scope so it neither sprawls nor omits.
- **Diagram set** — default: one **activity** diagram (control flow, decisions, cross-system swimlanes) plus one or more **sequence** diagrams (temporal message exchange between participants). Add a **system-context** panel when there is more than one system, and a **data-model** panel when persistence is central to the subject. Don't force a diagram the ask doesn't need.

If scope is genuinely ambiguous — which repos, which phases — ask once, concisely, before the expensive mapping step. Otherwise proceed.

## Step 2 — Map the code

This step is where accuracy comes from. Walk each repo and build an internal **flow spec** — the raw material every diagram is generated from.

For breadth, dispatch `Explore` (or `general-purpose`) agents, one per repo or subsystem. They read widely and report `file:line`-accurate names, so you get the conclusion without the file dumps. Ask each agent to return:

- **Ordered steps** of the subject's journey — each with `file:line` and the real method / route / job / command / listener name.
- **Decision points** — every branch, guard, validation, and failure path. These become activity **diamonds** and sequence **`alt` / `opt`** fragments. They are the most valuable part: the plain happy-path spine is easy; the branches are what a reader can't infer themselves.
- **Participants** — the objects, services, controllers, jobs, and stores that send or receive. These become sequence **lifelines** and activity **swimlanes**.
- **Persistence** — the tables/models the subject touches, with key columns and the unique/link keys (foreign keys, pivots). These become the **data-model** panel.

Then **verify** before drawing. A diagram citing a class or route that doesn't exist is worse than no diagram. Read or grep the specific files an agent cited for anything load-bearing. For multi-repo subjects, map each repo separately and be explicit about the seam: what crosses it (the payload shape), and whether the hop is synchronous or asynchronous.

Record uncertainty honestly — if a branch's behaviour isn't clear from the code, it is an *inferred* node, and the diagram must say so.

## Step 3 — Calibrate the design

**Load the `artifact-design` skill before writing any HTML.** It sets the palette / type / layout discipline and, critically, the light-and-dark theming contract every artifact must honour.

These artifacts are **technical documents**, not landing pages — aim for utilitarian-polished, a "drawing-sheet" aesthetic:

- A **monospace-forward** type treatment fits the subject's vernacular (class names, routes, node labels in mono; running prose in a humanist sans) and sidesteps the generic AI look.
- Use **hue to encode system ownership** — one accent per repo / swimlane. That is information design, not decoration, so two accents here is legitimate. Keep **semantic** colours (warn / reject / ok) as a separate, third channel.
- Choose a neutral with a slight bias toward the accent; design both themes through tokens.

## Step 4 — Build the artifact

Start from **`assets/artifact-scaffold.html`** — it ships the theme-aware token system (light/dark + `data-theme` override), the drawing-sheet header, section scaffolding, the SVG `<defs>` arrow markers, and the styled SVG primitive classes. Fill it in rather than rebuilding from scratch.

Read **`references/svg-uml-kit.md`** for the full drawing kit: the UML-element → SVG mapping, copy-pasteable primitives (action node, decision diamond, terminal, swimlane, lifeline, activation bar, sync/async/return arrows, `alt`/`opt`/`loop` fragments), the coordinate discipline that keeps a hand-drawn diagram clean, the two layout patterns (swimlane "staircase" for activity; participant grid for sequence), and the pitfalls that cause overlaps and mis-routed arrows.

Rules that keep the result legible and correct:

- **One diagram, one job.** Split into several diagrams — an activity diagram plus per-phase sequence diagrams — rather than one unreadable mega-SVG.
- **Every wide diagram scrolls in its own container** (`overflow-x: auto`), so the page body never scrolls sideways on a phone.
- **Theme both modes through tokens.** Never hard-code a colour inside a shape; style SVG shapes with classes that read CSS custom properties.
- **Footer of sources.** List the real `file:line` / class anchors the diagrams were drawn from, and flag any inferred nodes. The diagram is a claim about the code — make it auditable.
- **Copy stays true to the code** (real names), with a one-line note wherever you made a modelling choice (e.g. what a particular score band means, or that two branches were collapsed for space).

## Step 5 — Publish and confirm

Publish the self-contained HTML with the Artifact tool. Give it a stable, descriptive `<title>` and a favicon, and a one-sentence `description` (the Artifact tool's gallery subtitle — say what the diagram set covers, e.g. "UML activity + sequence diagrams of the checkout order lifecycle"). Keep the title and favicon stable across redeploys of the same artifact; editing the file and re-publishing to the same path redeploys to the same URL.

After publishing, surface the **modelling judgement calls** you made — a band split, a collapsed branch, an inferred path, a diagram you chose not to draw — and offer to adjust. The reader knows the domain; you know the code; the good version is the intersection.

## Guardrails

- **Accuracy over completeness.** Verified real names only. Mark inferred nodes. Never invent flow to fill a diagram.
- **No Mermaid / PlantUML / kroki / CDN anything.** Inline, hand-authored SVG only — the CSP blocks the alternatives silently.
- **Match the diagram set to the prompt.** Don't ship a sequence diagram for a pure control-flow ask, or an activity diagram when the question is really "who calls whom, in what order".
- **Multi-repo: name the seam.** State what crosses each boundary and whether it is sync or async; colour lanes by owning system.
- **Legibility beats density.** Split diagrams, scroll wide ones, theme both modes, don't overcrowd a lane.
- **Cite sources in a footer.** Auditable or it didn't happen.

## No repository to read

If asked to diagram a process with no code to inspect (a described workflow, a proposed design), you can still draw it — but say plainly that the diagram reflects the **description**, not verified code, and drop the `file:line` source footer (there are no anchors to cite). Offer to re-ground it once the code exists.
