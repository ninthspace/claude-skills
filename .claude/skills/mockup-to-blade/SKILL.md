---
name: mockup-to-blade
description: "Convention: `…-mockup(s)` produces a mockup; `mockup-to-…` consumes a mockup, builds the named target. Siblings: `brief-to-mockups` and `filament-mockup` produce mockups; `mockup-to-filament` is the Filament build lane. Use this skill when turning an HTML mockup into a production bespoke Laravel UI (Blade/Livewire) and you want the result to match the mockup closely. Trigger when converting bespoke/branded mockups/showcases/wireframes to hand-authored Blade/Livewire, when a delivered .html mockup must become real pages, when asked to 'build to match the mockup', 'implement the design', 'wire up the screens', or when extracting components/tokens/copy from a mockup. Covers: decomposing a multi-screen HTML showcase into routes, classifying each screen's surface vs mechanism, extracting presentational Blade components with verbatim classes, locking design tokens/component contracts/copy, wiring one global stylesheet (Vite app.css), parity + coverage gates, and the predictable correction seams. Do not use for Filament surfaces (admin/CRUD/back-office panels) — those are a different substrate whose fidelity rule is shared theme not shared markup, and go to `mockup-to-filament`; and not for greenfield UI with no mockup, or for non-Laravel stacks."
license: MIT
metadata:
  author: ninthspace
  version: "2.0"
  companion: references/mockup-to-implementation-fidelity.md
---

# Mockup → Implementation Fidelity

Execute this method to turn an HTML mockup into a production **bespoke** Laravel UI (Blade / Livewire) that matches the mockup **by construction**. (Filament surfaces are a different substrate — see Step 0; they belong to `mockup-to-filament`.) This skill is self-contained: the full step backbone is embedded below — you do **not** need to open any other file to run it. The deep companion with worked examples and rationale is **bundled with this skill** at `references/mockup-to-implementation-fidelity.md` (read it when you want the *why* behind a step, or a worked example to copy). It travels with the skill — it is not a reference to any project's `docs/`.

## How to use this skill

Work the steps **in order**. Each step has a **Rule** (the fidelity-critical constraint — skipping it reintroduces drift) and a **Done when** gate. Do not advance past a step until its gate is met. Honour the **Ordering guardrails** below — they are hard preconditions, not suggestions.

**Step 0 is a refuse-to-run guard**: confirm the surface is `bespoke` before touching anything — a `filament` surface is handed off to `mockup-to-filament`. After that, the method has two halves: Steps 1–6 make the UI match by construction; Step 7 is the correction-seams checklist for the predictable static-mockup-meets-live-system gaps.

## The bespoke fidelity principle (read once before starting)

A mockup and its production page diverge at every point where they are *different artifacts*. For a **bespoke** surface the method collapses those points: build the mockup in the same substrate as production, extract its markup into components production reuses **verbatim**, wire its stylesheet once, gate with tests. **This "one shared component, classes verbatim" rule is the *bespoke* definition of fidelity — it is not universal.** The Filament substrate inverts it (fidelity = a shared *theme*; the mockup markup is throwaway and Filament regenerates it), and that lane is `mockup-to-filament`'s — Step 0 has already sent any `filament` surface there before you reach this principle.

**The mockup is the *visual* authority — not the *integration-mechanism* authority.** It settles what the user sees (layout, classes, copy, which screens exist). It does **not** settle how a screen is wired to the backend (which guard validates a login, which IdP issues a token, which service backs a field). Mockup *comments* about wiring are a designer's guess, not a spec decision. When a comment and the prior art / spec ADs disagree on a mechanism, treat the mockup as visual-only and reconcile the mechanism against the **spec's architecture decisions + prior art** (confirm with the human if still ambiguous). Conflating "the mockup shows a login page" with "the mockup says how login works" is how a real, paint-it-yourself surface gets wrongly dismissed as "the framework handles that" — see **G7**.

## Where this sits in the lifecycle (run before spec→epics)

Steps 1–4 are a **Phase 0**: ideally run them — decompose the mockup, extract the full component set, lock tokens/contracts/copy — **before the spec is decomposed into feature epics**. Feature epics should be authored against a *complete, gated* component substrate they merely consume; if the substrate doesn't exist yet, each feature epic silently scopes extraction to its own screens and the inventory ends up partial (see G6/G7). The expensive failure mode is retrofitting a "UI foundation" epic *after* the feature epics are written: extraction then gets scoped to whatever screens those epics happened to render first, the mockup's other screens ship un-extracted, and the feature-epic docs need rewriting to point at a scaffold that arrived late. If you must retrofit, the coverage gate (Step 6) is what stops the foundation from shipping partial.

## Ordering guardrails (do not reorder)

These are hard preconditions. Before doing the work of a step, confirm its guard holds; if it does not, go back. Violating one is the most common way fidelity is lost.

- **G1 — Classify before you route (gates Step 1).** Do not create any route or Blade view until every screen has an assigned target surface (route / wizard-step / email / parent-state / surface-less handoff). Routing before classifying produces over-routed wizards and web pages that should have been emails.
- **G2 — Extract components (Step 2) before assembling production pages (Step 5).** Never build a production page out of hand-rolled markup intending to "componentise later". The page must consume components that already exist, or the mockup and production drift into two definitions.
- **G3 — Stand up the route-render gate (Step 6) early — right after Step 1 produces routes**, not at the end. The gate then protects every subsequent step; deferred to the end, it catches nothing along the way.
- **G4 — Wire the one stylesheet globally (Step 4) before per-page styling judgements.** If a page needs page-level CSS to match the mockup, the global wiring is incomplete for that surface — fix G4, do not patch the page.
- **G5 — Tokens/contracts/copy stay separate (Step 3).** Never let a copy edit live in a component, or a colour live in markup. One change must touch one locked home.
- **G6 — Extract from the *whole mockup*, not just the screens Step 1 rendered.** Step 1 only materialises *web surfaces* as views — wizard steps, email screens, and modal states are classified but never become standalone views. Component extraction (Step 2) must still cover them: inventory every `#screen-*` in the source mockup, including the ones that aren't routes. Scoping extraction to the rendered subset is how repeated blocks on un-rendered screens ship un-extracted (e.g. a `select-card` used 100+ times across three never-routed wizard steps). The coverage gate (Step 6) enforces this.
- **G7 — Classify a screen's SURFACE separately from its MECHANISM; "a framework handles it" is a statement about the mechanism, never an excuse to skip the surface.** Every screen has two independent facets: its **surface** (the branded page the user sees — markup, classes, copy → needs extraction + a route) and its **mechanism** (how it's wired — guard, validation, IdP, token handoff → may be owned by a different epic). A framework/IdP owning the *mechanism* says nothing about who builds the *surface*. A login page is the canonical trap: "Fortify/SSO handles auth" is true of the mechanism and irrelevant to the surface — the bespoke sign-in page is a real web surface needing extraction like any other. **Only a screen with no surface of its own is exempt**: a tokenised redirect (`GET /auth/sso?token=…` → 302, renders nothing) draws nothing, so there's nothing to extract. The discriminator is *"does this screen paint pixels the user sees?"*, not *"does a framework sit behind it?"*. G7 generalises G6 from *deferred* screens to *dismissed* screens; both ship a real block family un-extracted and both are caught by the Step 6 coverage gate.

**Dry-run self-check (run before executing, and when validating this skill).** Emit the plan as an ordered list and confirm it reads exactly:

```
0. Confirm the substrate is bespoke (routing-table `substrate` column, or human) — hand any `filament` surface to mockup-to-filament; never assume bespoke on a missing/ambiguous table (Step 0 guard)
1. Decompose showcase → classify every screen by SURFACE + note MECHANISM separately → create routes/views (G1, G7)
   └ stand up the route-render parity test now (G3)
2. Extract repeated markup → anonymous components, classes verbatim — WHOLE mockup, incl. login/auth surfaces, not just routed screens (G6, G7)
3. Lock the three sources: tokens (@theme), component contracts, copy
4. Wire the one stylesheet globally — Vite app.css (G4)
5. Assemble production pages from the SAME components (requires Step 2 — G2)
6. Complete the parity gates: coverage gate (every block classified; "handled elsewhere" reasons name the owning epic) + component smoke tests + the human visual pass
7. Walk the correction-seams checklist on every screen
```

If the emitted order differs from the above — or if Step 0's substrate guard was skipped (no confirmation the surface is `bespoke`), or Step 5 appears before Step 2, or Step 6's route test is deferred past Step 1, or a login/auth screen is classified "framework handles it" with no surface work — stop and correct before doing any work. Steps 1–6 build by construction; Step 7 is the correction-seams pass.

## The step backbone (embedded — this is the source of truth for execution)

### Step 0 — Confirm the substrate is bespoke  *(refuse-to-run guard — entry step)*

`mockup-to-blade` builds **bespoke** Blade/Livewire surfaces only — surfaces whose markup you author and whose fidelity rule is "one shared component, classes verbatim". Filament surfaces are a *different substrate* with the inverted rule (fidelity = shared **theme**, not shared markup; the mockup Blade is throwaway and Filament regenerates the markup) and belong to **`mockup-to-filament`**. This guard is a **hard stop, not an internal branch** — running the bespoke verbatim-classes method on a Filament surface is the exact contamination this split exists to prevent (see AD1).

Do: read the routing table at `docs/mockups/surface-routing.md` (schema `surface · FRs · substrate · producer · builder`) and check its `substrate` column for the surface(s) you are about to build.
- **`substrate = bespoke`** → proceed to Step 1.
- **`substrate = filament`** → **stop and hand off to `mockup-to-filament`.** Do not decompose, extract markup, or reuse mockup classes for that surface.
- **No routing table, or the substrate is missing/ambiguous for a surface** → **do not assume bespoke.** Ask the human which substrate the surface targets (or stop and report that the routing table is missing/ambiguous). Proceed only once `bespoke` is confirmed. This is the conservative side of the contract: `mockup-to-filament` proceeds as Filament when no table exists (the legitimate all-Filament-brief path), but for `mockup-to-blade` silence never means bespoke.

**Rule**: never run a single step of this method on a `filament` surface, and never treat an absent or ambiguous routing table as permission to proceed — confirm `bespoke` explicitly (table or human) first.

**Done when**: every surface you will build is confirmed `bespoke` (via the routing table or explicit human confirmation); any `filament` surface has been handed off to `mockup-to-filament`; a missing or ambiguous table was resolved by asking/stopping, not assumed bespoke.

### Step 1 — Decompose the mockup into real Blade views at real routes

A delivered mockup is usually one HTML file holding many screens behind a JS switcher (`<nav class="mockup-nav">` + `<div id="screen-x" class="screen">` blocks, one `.active`, toggled by `showScreen()`).

Do: (1) catalogue every `#screen-*`; (2) **classify each by its real surface** — full-page route (incl. any bespoke `login` page) / step within one multi-step Livewire flow / email (Mailable) / transient state of another page / genuinely surface-less handoff (tokenised redirect that renders nothing); (3) **for each screen, record surface *and* mechanism separately (G7)** — what it looks like (→ extraction + route) vs how it's wired (→ guard/IdP/service, maybe a different epic); a mechanism note never deletes surface work; (4) strip the showcase scaffolding (`.mockup-nav`, `showScreen()`, `.screen`/`.active`); (5) create a real route + view per web surface, reading `showScreen('y')` targets as the navigation graph; (6) lift the inline `<head>` `<style>`+fonts into the project (forward to Steps 3–4).

**Rule**: classify before you route, and separate surface from mechanism — a screen is **not** automatically a page, and a framework owning a screen's *mechanism* never deletes its *surface*. Never reproduce the showcase switcher in production. (Wizards = one stateful component, not N routes; email screens = Mailables, not routes; modal/empty screens = states of a parent; a **login page is a real surface** even when a framework/IdP handles the credentials; only a render-nothing tokenised redirect is genuinely surface-less.) If a mockup comment asserts a mechanism that contradicts the prior art/spec, the mockup is visual-only — reconcile against the spec ADs + prior art.

**Done when**: every screen is catalogued with a decided target surface *and* a separately-noted mechanism; any login/auth surface is kept as a real route (its mechanism noted separately, possibly owned elsewhere); a real route renders for each web surface inside the project layout; the showcase scaffolding is gone; the Step 6 route-render test (stood up now) asserts 200 for each new route.

### Step 2 — Extract markup into presentational components, classes verbatim

Do: inventory repeated markup across the **whole mockup — every `#screen-*`, not just the screens Step 1 rendered as routes** (wizard steps, email screens, modal states, **and login/auth surfaces** count, even though they aren't routes or are "framework-handled" — see G6 + G7); promote each block to an anonymous Blade component (`resources/views/components/...`, `@props([...])`). Move markup + classes only — no `auth()`/`route()`/model/service reads inside the component; state and `href` arrive as props/slots. Several block-class families may map to one component (variant classes, BEM children, paired blocks like `login-page`/`login-wrap`).

**Rule**: keep the CSS class names **verbatim** (`.screen`, `.stepper__step`, `.login-card`, …). Do not rename classes or swap them for ad-hoc Tailwind utilities during extraction — the componentised form must render identically because it *is* the mockup's markup. And extract **whole-mockup**: the denominator is every repeated block in the source file, not the subset of screens already routed, and not minus the screens a framework "handles". Building a login *surface* does not mean wiring the auth *mechanism*.

**Done when**: every repeated block **across all screens — incl. wizard steps, emails, modal states, login/auth surfaces** — is an anonymous component with verbatim classes; no component reads state; a mockup view re-pointed at the components renders identically (route-render test still green); the Step 6 coverage gate confirms no block-class family is left un-extracted-and-unclassified.

### Step 3 — Lock three sources of truth: tokens, component contracts, copy

Three independent drift surfaces, three locked homes:
- **Tokens** — extract the mockup `:root` custom properties (palette, gradients, font, spacing, radii); store in Tailwind v4 `@theme` in `resources/css/app.css` (keep raw `--*` custom props for the component CSS to reference); lock = no hard-coded hex in markup.
- **Component contracts** — inventory → anonymous components with explicit prop/slot contracts; store components in `resources/views/components/...` + a component-reference doc; lock = pages consume `<x-...>` by contract, never copy markup.
- **Copy** — extract every user-facing string incl. email subjects/bodies; store in a locked copy reference (doc or lang files); lock = production pulls strings verbatim, nobody paraphrases.

**Rule**: three sources, three locked homes, never conflated — if editing one changes another, they are entangled.

**Done when**: tokens in `@theme` (no hard-coded hex); a component-reference lists props/slots/variants/consumers; a copy reference holds every string (incl. emails) pulled verbatim.

### Step 4 — Wire the one stylesheet into production, globally

Import the single consolidated stylesheet **once** for the whole bespoke surface — via the layout `<head>` / Vite `app.css` entry — so every page (customer flow, public journey, bespoke Livewire) inherits it. (Filament admin theming via `->viteTheme(...)` is a different substrate and lives in `mockup-to-filament`; a `filament` surface never reaches this step — Step 0 handed it off.)

**Rule**: one wiring, applied globally — **never re-skin page by page**. If you're adding CSS to an individual page to match the mockup, the global wiring is missing for that surface; fix the wiring, not the page.

**Done when**: bespoke pages render styled with zero page-level CSS, all from the single Vite `app.css` entry; grep finds no per-page `<style>` reproducing mockup rules.

### Step 5 — Assemble production pages from the same components

Do: build each production page (Blade view / Livewire-Volt component) by composing the **same** Step 2 components, fed real state through props. The page owns data + wiring; components own appearance. A login/auth surface is assembled here from its `login-*` components on **fixtures** — the owning feature epic later binds `wire:model`/validation/the guard onto the *same* markup, extending the scaffold rather than re-skinning it.

**Rule**: production consumes the components; it does not re-implement their markup. One component definition, shared by mockup and production.

**Done when**: each production page is assembled from the components; no page re-implements component markup inline; swapping a component's markup updates both mockup and production.

### Step 6 — Stand up parity gates: route-render tests + coverage gate + smoke tests

Do: (1) a Pest feature test hitting every mockup route asserting 200; (2) a smoke test rendering each component with representative props — slot-container components with no representative props of their own (page wrapper, card shell) are smoke-tested via the **rendered page that assembles them**, not in isolation; (3) a **coverage gate** — a test that extracts every block-class family from the mockup markup (each `class="..."` token reduced to the part before its BEM `__`/`--` separator) and asserts each is either mapped to a component or explicitly classified in a manifest, with a **reason**; (4) reserve a **human visual pass** for true visual parity.

**Rule — the gates guard structure + coverage; only a human guards visual parity; and the manifest reason field is load-bearing.** A green suite proves pages/components *render* and that every block *has a home*; it does **not** prove they *look right* — do not assert visual equivalence by parsing HTML. The coverage gate makes G6 *and* G7 enforceable: a new block fails until it is a component or classified. **The danger cells are the "handled elsewhere" classifications** (`out-of-scope`, `framework-native`, `'fortify'`, `'auth'`) — these silently skip extraction, so they are where a real surface goes to die. Require every such cell to **name the concrete owning artefact**, never a framework word: not `'fortify'` but `'surface built in 01-10 Story 5; mechanism wired in 01-04 Story 1'`. A reason that names an epic is auditable; a reason that names a framework is an alibi — if you can't name where the surface is built and the mechanism wired, the cell is unclassified. Likewise a **must-NOT** criterion (this surface must *not* be wired to auth / must *not* route through the handoff) can't be self-asserted — write a test that **names the wrong-flow artefact and proves its absence** (no shared action, no `auth` middleware, renders 200 unauthenticated, links to neither the handoff nor the admin redirect).

**Done when**: a test asserts 200 for every mockup route (in CI); each component has a smoke test (slot containers via their assembled page); the coverage gate classifies every block-class family (no unclassified families, no stale entries) and every "handled elsewhere" reason **names the owning epic/story**; each must-NOT criterion has an explicit negative test; the PR/docs note that visual parity is a human pass, not the suite.

### Step 7 — Correction-seams pre-flight checklist

Walk every screen against these before calling it done — they recur on every build:
1. **Invented data** — mockup shows a field no column backs → check the schema for the specific column before promising it; add/derive/drop deliberately.
2. **Copy wrapped/pluralised in markup** — `<strong>`-split and `Str::plural` defeat naive `assertSee` → use `assertSeeText` + count-matched fixtures; assert on text not markup.
3. **States the mockup never drew** — empty/loading/error/one/many/overflow → enumerate and design each.
4. **Inline-Blade pitfalls** — `@php(...)` and `@if` glued to text fail to compile → use `{{ ternary }}` for inline conditional text, `@php ... @endphp` or a view-model method for logic.
5. **Faked client-side interactions** — `onclick="...toggle"`, `showScreen()`, a fake `onsubmit` that jumps screens, prefilled credentials, dead `#` links are illustrations → re-implement as real Livewire/Volt state + real navigation (and real/external links for login's forgot-password/register); read the JS for intent, then discard it.
6. **Email "screens"** — Mailables can't use the shared stylesheet → build with inline-safe styling; verify in a mail previewer, not the route-render test.
7. **Mechanism mismatch (G7's seam)** — a mockup comment names a wiring (`<!-- tokenised request from the administrator SSO -->`) that may contradict the spec ADs / prior art → treat the mockup as visual-only, reconcile the mechanism against the spec + prior art (confirm with the human), and build the surface regardless of the mechanism debate.

**Rule**: treat the seams as a pre-flight checklist, not after-the-fact debugging — a seam caught on the list is a quick decision; caught at render time it's a rework cycle.

**Done when**: every screen has been walked against seams 1–7, each applicable seam resolved or explicitly noted out of scope.

## The bundled companion

`references/mockup-to-implementation-fidelity.md` (bundled in this skill directory) is the long-form version of everything above — same seven steps, with the *why* spelled out, the full correction-seams reasoning, and **worked examples** from real bespoke builds (the user registration system: the `select-card` G6 miss and the `login-*` G7 surface-vs-mechanism miss; plus route-render, extraction, token and seam examples). Filament worked examples have moved to `mockup-to-filament`. Read it when you want the rationale behind a rule or a concrete pattern to copy. The embedded backbone above is sufficient to execute the method; the companion is depth, not a dependency.
