---
name: brief-to-mockups
description: "Convention: `…-mockup(s)` produces a mockup; `mockup-to-…` consumes a mockup, builds the named target. Siblings: `filament-mockup` also produces (Filament panels); builders `mockup-to-blade` (bespoke) and `mockup-to-filament` (Filament) consume mockups. Use this skill to turn a product brief (with functional requirements) into a set of mockups backed by a single style guide. Trigger when asked to 'mock up the screens from the brief/spec', 'produce mockups from the functional requirements', 'generate a style guide and mockups', 'turn this brief into a clickable mockup', 'design the screens for these FRs', or when starting the visual phase of a CPM/BMAD pipeline before the spec is written. Produces a stack-agnostic single-file HTML showcase plus a locked style-guide artifact, grounded so every screen element traces to an FR. Sits UPSTREAM of the build lane — mockup-to-blade (bespoke) and mockup-to-filament (Filament), which turn the showcase into production code — and is the framework-agnostic sibling of filament-mockup (which targets Filament's grammar). Handles mixed briefs by routing at intake: Filament-bound surfaces (admin/CRUD/back-office) are handed off to filament-mockup, bespoke/branded surfaces continue here. Do not use to convert an existing mockup into production code (use mockup-to-blade or mockup-to-filament); if the ENTIRE brief is a Filament panel, invoke filament-mockup directly instead."
license: MIT
metadata:
  author: ninthspace
  version: "1.0"
  hard-dependency: frontend-design
  siblings: [mockup-to-blade, mockup-to-filament, filament-mockup]
  origin: a user registration system (worked example)
---

# Brief → Mockups (+ Style Guide)

Execute this method to turn a product brief into a **set of mockups** that are (a) visually coherent because they share **one** style guide, and (b) honest because **every element traces to a functional requirement**. The deliverable is a single self-contained HTML showcase plus a locked style-guide artifact — the visual specification that a spec is then written *against*.

This skill is self-contained: the phase backbone below is the source of truth. A ready-to-use showcase scaffold ships with the skill at `assets/showcase-scaffold.html` — copy it, don't hand-roll the harness.

## Hard dependency: `frontend-design`

This skill **must** invoke the `frontend-design` skill to do the visual-direction work — in **both** style-guide branches (extract and generate). `frontend-design` owns the *aesthetic* decision (palette, type pairing, layout concept, and the one **signature** element) and the anti-default critique. This skill owns the *pipeline* around it (intake → screen derivation → showcase assembly → coverage gate → handoff). Do not produce a palette or type system by hand; route that through `frontend-design` and crystallise its output into the locked style-guide artifact.

## Where this sits in the lifecycle (run before spec → epics)

The mockup is built **before** the spec is decomposed, because the mockup is what wins the room and then *feeds* the spec. Building production code here would front-run the spec. The pipeline is: **brief → mockups (this skill) → spec → epics → build (via `mockup-to-blade`)**. The showcase + style guide this skill emits are the exact input contract that `mockup-to-blade` consumes downstream — keep them clean.

## Two roadmaps: route by realisation target

A single brief often splits across surfaces that will be **built in different substrates**. Mock each surface in the substrate it will be realised in — a Filament admin panel mocked as bespoke HTML throws away fidelity and re-does work `filament-mockup` already does better, and vice versa. So before any design, partition the brief's surfaces by target (Phase 0) and send each partition down its roadmap:

- **Bespoke / branded surfaces** (customer-facing, public, marketing, anything with its own visual identity) → **continue in this skill** (Phases 1–6): generate/extract a style guide via `frontend-design`, build the branded HTML showcase.
- **Filament-bound surfaces** (admin panels, internal CRUD, administrator back-office) → **hand off to the `filament-mockup` skill**. Do *not* build a branded style guide or run `frontend-design` for these — `filament-mockup` mocks to Filament v5's own grammar and captures the real theme. This skill's job for that partition ends at the routing table.

A user registration system is the worked example: the customer registration journey went the bespoke roadmap; the administrator queue/panel went the Filament roadmap. Both are legitimate outputs of one brief.

> If the **entire** brief is a Filament panel, skip this skill and invoke `filament-mockup` directly. Use `brief-to-mockups` as the entry point when the brief is bespoke-only or **mixed**.

## The grounding principle (read once before starting)

A mockup is only useful as a spec input if it tells the truth about the brief. Two failure modes destroy that:

1. **Invented UI** — a screen or control that no FR or journey step asked for. It silently becomes a requirement the moment someone signs off the mockup. The coverage gate (Phase 4) exists to kill this.
2. **Incoherent visuals** — screens that each look plausible alone but don't share a system, because styling was decided per-screen instead of once. The locked style guide (Phase 1, enforced by G1/G4) exists to kill this.

The mockup is the **visual** authority — what the user sees (layout, palette, copy, which screens exist). It is **not** the integration-mechanism authority (which guard, which service, which IdP). That distinction is `mockup-to-blade`'s concern downstream; here, just don't assert mechanism in the mockup.

## Ordering guardrails (do not reorder)

Hard preconditions. Confirm each holds before doing a phase's work; if not, go back.

- **G0 — Route by realisation target before establishing any style guide.** Partition surfaces (Phase 0) into bespoke vs Filament-bound *first*. Never run `frontend-design` or build a branded style guide for a Filament-bound surface — that partition goes to `filament-mockup`. Skipping the routing produces a hand-built branded clone of an admin panel that Filament would render for free, and that `mockup-to-blade` then has to unpick.
- **G1 — Style guide before screens.** Do not write any screen markup until the style-guide artifact is locked (named tokens + chosen signature). Building screens first produces ad-hoc per-screen styling that never reconciles into a system.
- **G2 — `frontend-design` runs in BOTH branches.** Never skip it because a brand reference exists. Extraction *without* a deliberate design direction + anti-default critique yields a faithful-but-lifeless clone, or a brand mis-adapted to the new audience. In extract mode `frontend-design` is constrained (the existing brand is the pinned direction — its own rule: "the brief's words win"); in generate mode it runs full-bore.
- **G3 — Screens come from FRs, not imagination.** Every screen exists in the Phase 2 inventory; every element on a screen traces to an FR or a journey step. No element earns its place by looking nice.
- **G4 — Tokens live in `:root` only.** No hex literal, no inline colour, no per-screen font choice in screen markup. One visual change must touch one locked home. Copy lives as text in the markup but is reviewed as its own concern (Phase 3).
- **G5 — The nav switcher is throwaway harness.** The `.mockup-nav`, `showScreen()`, and `.screen.active` toggle are presentation scaffolding and must be labelled as such. `mockup-to-blade` discards them; production navigation is real routes/forms/state.
- **G6 — Coverage gate before sign-off.** Phase 4 runs before you hand anything off, not at the very end as an afterthought. Every FR mapped to ≥1 element; every element traced back. Unmapped FRs and unbacked elements are both defects.

### Dry-run self-check (emit before executing)

Emit the plan as an ordered list and confirm it reads exactly:

```
0.  Intake: read brief → extract FRs + journey → probe brand reference → route surfaces by target (Filament-bound → filament-mockup; bespoke → continue here) → write the durable routing artifact `docs/mockups/surface-routing.md` (`surface · FRs · substrate · producer · builder`) (G0)
1.  Establish the style guide (bespoke partition only): extract-or-generate — frontend-design MANDATORY in both → lock tokens + signature artifact (G1, G2)
2.  Derive the screen set from FRs + journey → screen-inventory table (G3)
3.  Build the single-file HTML showcase from the locked tokens; screens as #screen-* + throwaway nav (G4, G5)
4.  Coverage gate: FR→element audit across BOTH partitions; flag unmapped FRs and unbacked UI (G3, G6)
5.  Self-critique per frontend-design (anti-default, signature earns its boldness, quality floor)
6.  Handoff: position showcase + style guide for spec sign-off; write the managed pipeline block into the project CLAUDE.md so /cpm:epics front-loads the build foundation (`mockup-to-blade` for bespoke, `mockup-to-filament` for Filament)
```

If the emitted order differs — or Phase 3 appears before Phase 1, or `frontend-design` is skipped in the extract branch, or a screen has no FR backing, or a Filament-bound surface is about to get a branded style guide — stop and correct before doing any work.

## The phase backbone (source of truth for execution)

### Phase 0 — Intake & ground the subject  *(entry phase)*

Read the brief end to end. Produce four things:

- **FR list** — enumerate every functional requirement by id (FR-01…FR-N). Include promoted options (OPT-→FR) and any "must-NOT" constraints.
- **User journey** — the narrative of who does what in what order. This is what turns a flat FR list into a *sequence* of screens.
- **Reference probe** — does the brief name (or does the human have) an existing app, website, or product whose look should carry over? Record the answer; it selects the Phase 1 branch.
- **Realisation-target routing** — partition the brief's surfaces into **bespoke/branded** vs **Filament-bound** (G0). The discriminator is *"will this be built as a bespoke front-end with its own identity, or as a Filament admin/CRUD panel?"* — typically customer/public/marketing → bespoke; administrator/internal back-office → Filament. **Write this partition to a durable artifact at `docs/mockups/surface-routing.md`** — a standalone, diffable file that outlives the showcase HTML (not merely an inline table), with the schema:

  ```
  | surface | FRs | substrate | producer | builder |
  ```

  - `substrate` ∈ `bespoke · filament`
  - `producer` ∈ `brief-to-mockups · filament-mockup` — who wrote the row: this skill for mixed/bespoke briefs; `filament-mockup` on the all-Filament path where this skill is skipped
  - `builder` ∈ `mockup-to-blade · mockup-to-filament` — who realises the surface: a `bespoke` row → `mockup-to-blade`; a `filament` row → `mockup-to-filament`

  The Filament-bound rows are handed to `filament-mockup`; the bespoke rows continue here. A surface that's genuinely ambiguous gets confirmed with the human, not guessed. Persisting the table as a durable file (rather than letting it die at the mockup) is what stops the substrate being re-guessed at build time.

**Routing-artifact contract (read-first).** `docs/mockups/surface-routing.md` is the durable inter-skill contract between its **producers** — this skill for mixed/bespoke briefs, and `filament-mockup` on the all-Filament path where this skill is skipped — and its two **consumers**, the builders. **Both `mockup-to-blade` and `mockup-to-filament` read this artifact as their first act**: each builder's Step-0 substrate guard keys off the `substrate` column to refuse the wrong lane (`mockup-to-blade` stops/asks when it can't confirm `bespoke`; `mockup-to-filament` proceeds as Filament when the file is absent). Emit it here so that read-first contract always has something to read.

Per `frontend-design`'s "ground it in the subject": name the concrete subject, its audience, and each page's single job before designing. If the brief leaves the subject vague, pin it and state your choice.

**Rule (G0):** every later phase cites these artifacts. A screen with no FR/journey origin does not exist. No Phase 1 work begins until the routing table is settled.
**Done when:** you have a written FR list, a journey outline, a recorded reference-probe answer (reference / no reference), and the durable routing artifact `docs/mockups/surface-routing.md` (schema `surface · FRs · substrate · producer · builder`) assigning every in-scope FR to a bespoke or Filament partition.

### Phase 1 — Establish the style guide  *(bespoke partition only — the use-or-generate branch — `frontend-design` mandatory)*

This phase and Phases 2–6 operate **only on the bespoke partition** from the Phase 0 routing table. Filament-bound surfaces have already left for `filament-mockup` and get no branded style guide.

Produce **one** locked style-guide artifact. It holds: a named colour palette (4–6 hex values with semantic roles), a type system (display + body + utility faces with a scale), layout concept, the **signature** element, and component conventions (buttons, inputs, cards, badges, progress, modals). This artifact is the single source for the `:root` tokens in Phase 3.

Branch on the Phase 0 reference probe — **invoke `frontend-design` either way**:

- **Reference exists → EXTRACT, then adapt.** Capture the existing design system (palette, type, spacing, component patterns) into the artifact. Then run `frontend-design` in *honour-the-established-identity* mode: the existing brand is the pinned direction, so its job is the deliberate **adaptation** choice for *this* product's audience (e.g. a utilitarian form app → "warmer, more guided" for parents) plus the anti-default critique. Do not reinvent the brand; do make a deliberate, justified adaptation.
- **No reference → GENERATE.** Run `frontend-design`'s full two-pass process: brainstorm a compact token system → critique it against the three AI-default looks (cream/serif/terracotta; near-black/acid accent; broadsheet hairlines) → revise and justify → lock. The result becomes the artifact.

**Rule (G1, G2):** no screen markup exists yet. `frontend-design` was invoked. The artifact names every token a screen will need.
**Done when:** the style-guide artifact is written and locked, with named tokens, a stated signature, and component conventions — and you can point to the `frontend-design` output it derives from.

### Phase 2 — Derive the screen set from FRs + journey

Map the FRs and journey onto a concrete **screen inventory**. A screen is a distinct thing the user sees; a *state* is a variant of one (empty / loading / error / success / deadline-pending). Decide deliberately what is a screen vs a state — over-screening inflates the mockup, under-screening hides required states.

Emit a table:

```
| Screen / state | Kind (page · wizard-step · email · modal · state-of) | FRs covered | Journey step | Notes |
```

Cover the realistic states the brief implies (mid-flow, pending with deadline, empty queue, failure), not just the happy path — these are where requirements hide.

**Rule (G3):** every FR appears in the "FRs covered" column of at least one row before you build anything. Every row cites an FR or journey step as its reason to exist.
**Done when:** the inventory table accounts for all screens AND all FRs, with no orphan rows and no unmapped FRs.

### Phase 3 — Build the single-file HTML showcase

Copy `assets/showcase-scaffold.html` and fill it in. Structure:

- **`:root` token block** populated *only* from the Phase 1 artifact. No hex/font literal anywhere else (G4).
- **One `#screen-*` section per inventory row.** Tag each element that satisfies a requirement with `data-fr="FR-NN"` (and `data-fr` on the screen section for its primary FRs) — this makes Phase 4 mechanical.
- **The throwaway nav harness** (`.mockup-nav` + `showScreen()` + `.screen.active`) kept verbatim from the scaffold and clearly commented as harness (G5).

Execute each screen per `frontend-design`'s build principles (hero is a thesis, type carries personality, structure encodes meaning, motion is deliberate, restraint — spend boldness on the signature). Write copy per its writing guidance: end-user voice, active verbs, an action keeps its name through the flow, errors direct rather than apologise, empty states invite action.

**Rule (G4, G5):** tokens only in `:root`; harness labelled. Every requirement-bearing element carries a `data-fr`.
**Done when:** the file opens standalone, the nav switches between every inventory screen, and the design visibly applies the locked style guide on every screen.

### Phase 4 — Coverage gate (hard)

Two directions, both must pass — and coverage spans **both partitions**, so no FR falls into the crack between the bespoke showcase and the Filament mockup:

1. **FR → screen** — every FR from Phase 0 is satisfied by at least one `data-fr`-tagged element in the bespoke showcase **or** is owned by a Filament-bound surface in the routing table. List any FR satisfied by neither: that's a missing screen/element or a routing gap.
2. **Element → FR** — every meaningful element traces to an FR or journey step. List any element that doesn't: that's invented UI. Either find the FR that backs it, or cut it.

Emit a coverage audit (FR id → bespoke screen(s) where satisfied, *or* the Filament surface that owns it, plus an "unbacked elements" list). Because bespoke elements carry `data-fr`, you can extract the set of covered FRs directly from the file and diff against the Phase 0 FR list; reconcile the remainder against the routing table's Filament rows.

**Rule (G6):** runs before handoff. Unmapped FRs, FRs owned by neither partition, and unbacked elements are all defects.
**Done when:** every Phase 0 FR is accounted for across both partitions (modulo deliberately-deferred FRs, which are named), and the unbacked-elements list is empty.

### Phase 5 — Self-critique  *(`frontend-design`'s "critique again")*

Walk the showcase as the design lead: does any screen read like the generic default you'd produce for any brief? Does the signature element earn its boldness, with everything around it quiet? Is the quality floor met — responsive to mobile, visible keyboard focus, reduced-motion respected? Screenshot if the environment allows (a picture is worth 1000 tokens) and remove one accessory.

**Rule:** at least one concrete revision comes out of this pass, or you justify why none was needed.
**Done when:** you've recorded the critique and applied (or consciously declined) its changes.

### Phase 6 — Handoff

Position the artifacts for what comes next:

- The **style guide** → the locked visual source (and, if extracted, labelled as a dated snapshot of the reference, discardable at build time).
- The **bespoke showcase** → the visual spec the written spec is authored against, and the input `mockup-to-blade` decomposes. Re-state that the nav harness is throwaway.
- The **Filament partition** → confirm each routed surface was handed to `filament-mockup` and that its output exists, so the brief's two halves are both covered before the spec is written.

#### Write the pipeline-handoff block to the project `CLAUDE.md`

Downstream skills do not know this skill ran. In particular **`/cpm:epics` has no awareness of the lifecycle ordering** — left alone it will author feature epics that each scope component extraction to their own screens, and the UI foundation gets retrofitted *after* (the exact trap `mockup-to-blade`'s lifecycle warns against). Since `CLAUDE.md` is always in context when any skill runs, leave the standing guidance there.

Write (or update) a **managed, marker-delimited block** in the project's root `CLAUDE.md` — idempotent, so re-running this skill replaces the block rather than duplicating it. If the markers already exist, replace everything between them; otherwise append the block. Fold any pre-existing unmanaged equivalent instruction into the block so the guidance lives in one place.

```markdown
<!-- brief-to-mockups:start (generated — re-run the skill to update; edit outside these markers) -->
## Building UI from the mockups

**Artifacts** — bespoke showcase: `{showcase path}`; style guide: `{style-guide path}`; routing table: `docs/mockups/surface-routing.md`{; Filament partition: handled by filament-mockup}.

**Surface routing** — the durable table at `docs/mockups/surface-routing.md` (`surface · FRs · substrate · producer · builder`) is the source of truth for which lane builds each surface; each builder reads it first (its Step-0 guard). Bespoke: {surfaces}; Filament-bound: {surfaces, or "none"}.

**The fidelity rule is per-substrate, not global — build each surface in the lane its routing row names:**
- **`bespoke` rows → `mockup-to-blade`.** Fidelity = shared *markup*: presentational components with **verbatim classes**, one global stylesheet, tokens/contracts/copy locked separately, parity + coverage gates. **Never re-skin page by page.** *(This verbatim / never-re-skin rule applies to bespoke rows only.)*
- **`filament` rows → `mockup-to-filament`.** Fidelity = shared *theme, not markup*: configure native Filament constructs and carry the mockup's look through one theme; the mockup markup is throwaway and `fi-*` is never hand-extracted.

**Epic ordering (`/cpm:epics`)** — front-load the substrate foundation **before** any feature epic: for bespoke surfaces, run `mockup-to-blade` Steps 1–4 (decompose → extract the *whole* component set → lock tokens/contracts/copy → route-render gate); for Filament surfaces, run `mockup-to-filament`'s Phase 0 (theme + parity-deltas + `mk-custom` surfaces). Feature epics consume that gated/themed substrate; they must not re-extract components or re-theme per screen. Do not retrofit the foundation after feature epics are written.
<!-- brief-to-mockups:end -->
```

**Done when:** the bespoke artifacts are saved to the project's docs (style guide as a library/reference doc; showcase under `docs/mockups/` or equivalent), the Filament partition's mockup is accounted for, the handoff note names the throwaway harness and the next pipeline step, and the managed block is present and current in the project `CLAUDE.md`.

## Relationship to the sibling skills

- **`filament-mockup`** — the framework-specific variant and this skill's **routing target** for Filament-bound surfaces: same brief→mockup direction, but mocks to Filament v5's grammar and captures a real theme instead of building a brand style guide. Phase 0 hands it the Filament partition; for a wholly-Filament brief, invoke it directly and skip this skill.
- **`mockup-to-blade`** — the reverse direction: consumes this skill's **bespoke** showcase and turns it into production Blade/Livewire UI by construction. This skill's `data-fr` tags and clean token/harness separation are what make that decomposition cheap. (Its Filament counterpart is **`mockup-to-filament`**, which consumes `filament-mockup`'s output.)
