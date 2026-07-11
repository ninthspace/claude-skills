# Playbook: Mockup → Implementation Fidelity (Laravel / Blade / Livewire — bespoke lane)

**Purpose**: A concrete, repeatable method for turning HTML mockups into a production UI that matches them closely — by *construction*, not by chasing pixels afterwards. Written for a Laravel app using Blade and Livewire (Volt/Flux or class components). This is the **bespoke** build lane — Filament surfaces use a different substrate (fidelity = shared *theme*, not shared markup) and are covered by `mockup-to-filament`.

**How to read this**: The numbered steps are the method — follow them in order. The *fidelity-critical rule* in each step is the part that, if skipped, reintroduces drift. Each step ends with a **Definition of done**. Boxes labelled **Worked example** are evidence the method has been run for real; they are illustrative — the method stands without them. Paths in **ranking system** boxes live in that prior build (where this method was first proven); paths in **user registration system** boxes live in the build where the surface-vs-mechanism lesson (G7) was learned. They are citations, not files to open from another project.

**The shape of the method**: Two halves.

1. **Build-by-construction (Steps 1–6)** — structural choices that make the production UI *the same artifact* as the mockup, so parity is true rather than pursued.
2. **Correction seams (Step 7)** — the predictable places where a static mockup meets a live system and *always* needs a deliberate fix. These are not failures; they are known work. Pre-listing them is what stops "90% there" from stalling.

---

## The fidelity principle

> A mockup and its production page diverge at every point where they are *different artifacts*. The entire method is about collapsing those points: build the mockup in the same rendering substrate as production, extract its markup into components production reuses verbatim, wire its stylesheet in once, and gate the result with tests. What's left after that is the correction seams — and those you handle by knowing they're coming.

A Figma/PNG → code workflow can never give you this: the gap is reintroduced at every hand-off. The first move is therefore to get the mockup *into the codebase as real views*.

**The mockup is the *visual* authority — not the *integration-mechanism* authority.** It settles what the user sees: layout, classes, copy, the screens that exist. It does **not** settle how a screen is wired to the backend — which guard validates a login, which IdP issues a token, which service backs a field. Mockup *comments* (`<!-- tokenised request from the administrator SSO -->`) are a designer's guess at the mechanism, not a spec decision. When a mockup comment and the prior art / spec ADs disagree on a mechanism, treat the mockup as visual-only and reconcile the mechanism against the **spec's architecture decisions + prior art** (confirm with the human if still ambiguous). Conflating "the mockup shows a login page" with "the mockup says how login works" is exactly how a real, paint-it-yourself surface gets dismissed as "the framework handles that" — see G7.

---

## Where this sits in the lifecycle — run Steps 1–4 *before* spec→epics

The cheapest time to run this method is as a **Phase 0**, *before* the spec is decomposed into feature epics. Decompose the mockup (Step 1), extract the **full** component set (Step 2), and lock tokens/contracts/copy (Steps 3–4) up front — then author the feature epics against a *complete, gated* component substrate they simply consume.

The expensive alternative — the one to recognise and avoid — is **retrofitting a "UI foundation" epic after the feature epics already exist**. When the substrate is built late, component extraction gets implicitly scoped to *whichever screens the already-written feature epics happen to render first*. The mockup's other screens (wizard steps, email bodies, modal states, framework-handled surfaces) then ship un-extracted, the gap is invisible because the foundation "looks done", and you pay it back twice: re-scoping a supposedly-complete foundation, and rewriting feature-epic docs to point at a scaffold that arrived after they were written.

> **Worked example (user registration system):** the customer foundation (epic 01-10) *was* retrofitted after the nine feature epics. Step 2 was scoped to the four rendered web surfaces, so `select-card` — used 136× across three never-routed wizard steps — shipped un-extracted and was only caught by eye, post-"completion". The fix produced the coverage gate (Step 6) and guardrail **G6** below. If you must retrofit, that gate is what stops the foundation shipping partial; but the real lesson is the ordering — do the mockup and its components first.

**Guardrail G6 — extract from the whole mockup, not just the screens Step 1 rendered.** Step 1 only materialises *web surfaces* as views; wizard steps, emails and modal states are classified but never become standalone views. Step 2 must still cover them. The denominator for "every repeated block" is every `#screen-*` in the source file — not the subset already routed.

**Guardrail G7 — classify a screen's SURFACE separately from its MECHANISM; "a framework handles it" is a statement about the mechanism, never an excuse to skip the surface.** A screen has two independent facets:

- its **surface** — the branded page the user *sees and experiences* (markup, classes, layout, copy); and
- its **mechanism** — how it's wired to the backend (guard, credential validation, IdP, token handoff, service call).

A framework or IdP owning the *mechanism* says **nothing** about who builds the *surface*. A login page is the canonical trap: "Fortify/Sanctum/the SSO handles auth" is true of the mechanism and irrelevant to the surface — the bespoke-branded sign-in page is a real web surface that needs a route + extracted components like any other screen. Only a screen with **no surface of its own** is genuinely exempt from extraction: a *tokenised redirect* handoff (`GET /auth/sso?token=…` → 302, renders nothing) draws nothing, so there's nothing to extract. The discriminator is **"does this screen paint pixels the user sees?"**, not **"does a framework sit behind it?"**. G7 generalises G6 from *deferred* screens (wizard steps no route rendered yet) to *dismissed* screens (assumed handled by a framework). Both fail the same way — a real block family ships un-extracted — and both are caught by the same Step 6 coverage gate.

> **Worked example (user registration system):** `#screen-login` ("Sign in with your account") was classified as "Fortify / the administrator SSO handoff" and its ten `login-*` block families shipped **un-extracted** — the same shape as the `select-card` G6 miss, one layer subtler. Two things had been conflated: the customer **surface** (a bespoke branded login page the customer experiences — needs extraction + a `/login` route) and the customer **mechanism** (credentials validated against an external identity system on a `customer` guard). They were collapsed into the administrator **tokenised handoff** (`/auth/sso?id=&code=` → 302 → `/admin`), which genuinely *is* surface-less. The mockup comment ("tokenised request from the administrator SSO") was a mechanism guess that contradicted the prior art; per the fidelity principle, the mockup was visual-only and the mechanism was reconciled against the spec AD + prior art (an external identity system, confirmed with the product owner). Corrective work extracted the eight `login-*` components and stood up `/login` on fixtures (surface), leaving the mechanism to the feature epic that consumes them.

---

## Step 1 — Decompose the mockup into real Blade views at real routes  *(entry step)*

This is the move that gets the mockup *into the codebase* so every later step has something real to work on. A delivered mockup is usually a **single HTML showcase holding many screens** behind a client-side switcher. You have to take it apart correctly before you can build.

**The starting shape** (recognise it): one `.html` file with

- a showcase switcher — a `<nav class="mockup-nav">` of buttons calling a `showScreen('x')` JS function;
- many screen blocks — `<div id="screen-x" class="screen">`, exactly one carrying `.active`, with `.screen.active { display:block }` and the rest `display:none`;
- inline `<head>` assets — a `<style>` block (brand custom properties + all component CSS) and font links;
- in-screen navigation — `onclick="showScreen('y')"` on buttons/links, and small client toggles like `onclick="this.classList.toggle('selected')"`.

**What you do**:

1. **Catalogue every screen.** List each `#screen-*` id.
2. **Classify each screen by its *real* surface — do not assume one screen = one route.** This is the judgement that prevents the most expensive early mistake. Each screen is one of:
   - **A full-page route** — e.g. `landing`, `status`, **and the bespoke `login` page** (a login screen is a web surface — see G7; do not dismiss it as "the framework handles auth"). Gets its own route + Blade view (or Livewire/Volt page).
   - **A step within one multi-step flow** — e.g. `select-plan → … → review → confirmation` is an **8-step registration wizard**, which is **one** stateful Livewire/Volt component with internal step state, *not* eight routes. The `showScreen()` targets between these screens are *step transitions*, not URLs.
   - **An email / notification** — e.g. `receipt` ("Your registration confirmation"), `reminder` ("Upcoming appointment reminder"). These are **Mailables with markdown/Blade mail views**, not web routes. Building them as pages would be wrong.
   - **A transient state of another page** — a modal, an empty state, a confirmation overlay. These become a *state* of their parent view, not a standalone route.
   - **A genuinely surface-less mechanism** — and *only* this is exempt from extraction: a tokenised-redirect handoff that renders nothing (`GET /auth/sso?token=…` → 302). If a screen *draws* anything, it is a surface, not a handoff.
3. **Separate surface from mechanism for every screen (G7).** For each screen, record two things: *what it looks like* (its surface → extraction + route) and *how it's wired* (its mechanism → which guard/IdP/service, possibly owned by a different epic). Never let a mechanism note ("auth is handled by X") delete the surface work. If a mockup comment asserts a mechanism that contradicts the prior art or spec, the mockup is visual-only — reconcile the mechanism against the spec ADs + prior art (fidelity principle).
4. **Strip the showcase scaffolding.** The `.mockup-nav`, the `showScreen()` function, and the `.screen`/`.active` wrappers are presentation harness for the showcase — they are **not** application architecture. Discard them. Production navigation is real: route links, form submits, real auth, and Livewire step transitions inside the wizard.
5. **Create a real route + view per web surface** and render the screen's body inside the project layout. Read the `showScreen('y')` targets as the **navigation graph spec** — they tell you which screen flows to which.
6. **Lift the inline `<head>` into the project.** The `<style>` block becomes the consolidated stylesheet and its brand custom properties become the token layer (forward to Steps 3 and 4); font links move to the layout `<head>`.

**Fidelity-critical rule — classify before you route, separate surface from mechanism, and never reproduce the showcase switcher in production.** A screen is not automatically a page; equally, a framework owning a screen's *mechanism* never deletes its *surface*. Reproducing `showScreen()`/`.screen.active` client-side toggling — or minting one route per screen — bakes the *showcase's* structure into the *product*. Dismissing a real surface as "the framework handles it" bakes a *hole* into the product. Map each screen to its true surface (and note its mechanism separately) first; *then* build.

**Why**: The decomposition decides the entire downstream shape. Get the surface mapping right and Steps 2–6 operate on correctly-scoped views; get it wrong and you build eight thin routes for one wizard, a public web page for what should have been a Mailable, or — subtlest of all — *no* page for a login surface you assumed the framework would paint.

**Definition of done**:

- Every `#screen-*` is catalogued and assigned a target surface (route / wizard-step / email / parent-page state / surface-less handoff), with the wizard and email screens explicitly *not* turned into one-route-each, and any login/auth *surface* explicitly kept as a real route (its mechanism noted separately, possibly owned elsewhere).
- A real route exists and renders for each web surface, inside the project layout.
- The showcase scaffolding (`.mockup-nav`, `showScreen()`, `.screen`/`.active` wrappers) is absent from production views.
- The Step 6 route-render test (stood up early) hits each new route and asserts 200.

> **Worked example (ranking system):** the mockups were served as real Blade views through a controller carrying a registry of ~19 screens — `app/Http/Controllers/MockupController.php` and `resources/views/mockups/`. Each registry entry mapped a slug → view at a real route, so the mockups rendered in the *same substrate* as production rather than as static files.

---

## Step 2 — Extract markup into presentational components, classes verbatim

**What you do**: Inventory the markup across the **whole mockup — every `#screen-*`, not only the screens Step 1 turned into routes**. Wizard steps, email screens, modal states, **and login/auth surfaces** hold repeated blocks too (and they're the easiest to miss, because they never became standalone views, or because they were wrongly dismissed as framework-handled — see G6 and G7). Wherever a block of markup repeats (a card, a pill, a stepper, a stat tile, a slot), promote it to an **anonymous Blade component** (`resources/views/components/...`, props via `@props([...])`). Move *only* markup + classes into the component — no model lookups, no `auth()`, no `route()` state reads, no service calls. State and nav targets (`href`) arrive as props/slots.

**Fidelity-critical rule — keep the CSS class names verbatim.** The component's markup must be the *same markup* the mockup used, with the *same class names* (`.screen`, `.stepper__step`, `.login-card`, etc.). Do not rename classes to something "tidier", do not swap hand-written classes for ad-hoc Tailwind utilities during extraction. The componentised form must render byte-identically to the mockup because it *is* the mockup's markup, just lifted into a component. Renaming is the single most common way drift sneaks back in.

**Why**: If the component reproduces the mockup's exact class surface, then every page that uses the component inherits the mockup's appearance for free. Extraction becomes a *lossless* move rather than a re-interpretation. (Restyling — if wanted — happens later and globally, via the token layer in Step 3, never by editing markup.)

**Notes for this stack**:

- Anonymous components (no PHP class) are the right default for presentational primitives. Reach for a class-based component only when you need computed props.
- Keep components dumb. A component that does a lookup is a page concern leaking into a primitive — it will fight you at reuse time.
- A login/auth surface is built on fixtures here exactly like a wizard step: the components are dumb, the mechanism (guard, validation, IdP) is wired later by whichever epic owns it. Building the surface does **not** mean wiring the auth.
- Group components by surface (`components/customer/`, `components/admin/`, flat primitives at `components/`) mirroring the mockup's own grouping. Several block-class families may map to one component (variant classes, BEM child classes, paired blocks like `login-page`/`login-wrap`).

**Definition of done**:

- Every repeated mockup block **across all screens — including wizard steps, emails, modal states, and login/auth surfaces** — exists as an anonymous component consuming verbatim classes.
- No component reads state (no `auth()`/`route()`/model/service inside the view).
- A mockup view re-pointed at the components renders identically to its pre-extraction form (eyeball + the Step 6 route-render test still passes).
- The Step 6 **coverage gate** confirms no block-class family in the mockup is left un-extracted and unclassified.

> **Worked example (ranking system):** a `ui-style-guide-analyzer` inventory pass promoted the mockup markup to 31 presentational anonymous components under `resources/views/components/ranking/`, **class names preserved verbatim** (`.admin-*`, `.sl-*`, `.rank-*`); the token layer swapped hex literals for `var(--ranking-*)` *inside the stylesheet only*, leaving markup untouched. See `docs/design/03-design-components.md`.

---

## Step 3 — Lock three sources of truth: tokens, component contracts, copy

A mockup settles **three** independent things, and each drifts in its own way. Treat them as three separate locked references — change one, and it's a deliberate edit in one place. Conflate them and a copy tweak quietly restyles a component, or a colour change silently rewords a button.

### 3a. Visual tokens

- **Extract**: the mockup's `:root` custom properties — the colour palette, gradients, the font, spacing (`--space-*`), and radii (`--radius-*`).
- **Store**: in **Tailwind v4's CSS-first `@theme`** block in `resources/css/app.css` (`@import 'tailwindcss'; @theme { … }`). Bring the brand palette in as `@theme` tokens so Tailwind also generates utilities for new work; set `--font-sans` to the brand font. Keep the raw `--*` custom properties available too, because the extracted component CSS references them via `var(--…)`.
- **Lock**: markup and components **never** hard-code a hex value — they reference a token. One palette edit re-tints the whole app.

### 3b. Component contracts

- **Extract**: run an inventory pass over the split mockup views and promote each repeated block to an anonymous Blade component (Step 2), each with an explicit **prop/slot contract** — what data it takes, what variants it supports, what stays a slot.
- **Store**: the components in `resources/views/components/…`, and a **component-reference doc** listing every component with its props, slots, variants, and downstream consumers. The doc is the component *API*; the Blade files are its implementation.
- **Lock**: downstream pages consume `<x-…>` by its documented contract instead of copying markup. A new prop is a deliberate contract change, recorded in the reference.

### 3c. Copy

- **Extract**: every user-facing string the mockup settles — page headings, button labels, helper/empty-state text, validation messages, and **email subjects + bodies**. The mockup is a *copy* decision as much as a layout one.
- **Store**: a **locked copy reference** as the authoritative string source — or Laravel lang files if you prefer runtime lookup. Production pulls strings from it verbatim.
- **Lock**: nobody paraphrases a label into "looks right but reads wrong". Copy is where mockups silently drift, because a reworded button still *looks* identical.

**Fidelity-critical rule — three sources, three locked homes, never conflated.** Tokens, component contracts, and copy each get their own authoritative location. If a single edit to one of them changes another, they are entangled and will drift — separate them.

**Definition of done**:

- The mockup's `:root` tokens live in `@theme` (+ retained `--*` custom properties); no hard-coded hex in markup or components.
- A component-reference doc lists each component's props/slots/variants/consumers.
- A copy reference (doc or lang files) holds every user-facing string, including email subjects/bodies, and production pulls them verbatim.

> **Worked example (ranking system):** the three sources were locked in three separate docs — tokens in `docs/design/01-design-visual-system.md`, component contracts in `docs/design/03-design-components.md`, and copy in `docs/design/02-design-ranking-copy.md`, which downstream specs cite as "the authoritative string source".

---

## Step 4 — Wire the one stylesheet into production, globally

**What you do**: Make the mockup's stylesheet *the* production stylesheet through a single global integration point — never page by page. Import the single consolidated stylesheet **once** in the layout `<head>` (or once through the Vite `app.css` entry) for the whole bespoke surface — a customer flow, a public journey, a bespoke Livewire panel. Every page under that layout inherits the mockup's CSS with no per-page work. (Filament admin theming via `->viteTheme(...)` is a different substrate handled by `mockup-to-filament`; a `filament` surface never reaches this method — see Step 0 of the skill.)

**Fidelity-critical rule — one wiring, applied globally; never re-skin page by page.** The moment you find yourself adding styles to an individual production page to "make it look like the mockup", stop: that means the global stylesheet isn't wired to that surface. Fix the wiring, not the page. Page-by-page skinning is exactly where drift breeds.

**Why**: This is the highest-leverage step. A single correct wiring decision can satisfy the styling of *every* page on a surface in one move — collapsing what looks like N "convert this page" tasks into "wire it once, then verify".

**Notes for this stack**:

- Tailwind v4 is CSS-first: the token layer (Step 3) lives in `@theme` inside `app.css`, and the consolidated component CSS lives alongside it (or is `@import`ed). The "one stylesheet" is then just the `app.css` Vite entry.
- Load the consolidated stylesheet **once** per page. A primitive used 10× on a page must still ship its CSS once — consolidate, don't inline per-component `<style>`.

**Definition of done**:

- Bespoke production pages render styled with **zero** page-level CSS added — styling arrives entirely through the single global include.
- Grep finds no per-page `<style>` blocks or page-scoped stylesheet includes reproducing mockup rules.

---

## Step 5 — Assemble production pages from the same components

**What you do**: Build each production page (Blade view or Livewire/Volt component) by composing the **same** components extracted in Step 2 — feeding them real state through their props. The production page's job is data + wiring; the components own appearance. The mockup view and the production page should differ only in *where the props come from* (hard-coded in the mockup, live data in production).

**Fidelity-critical rule — production consumes the components; it does not re-implement their markup.** If a production page hand-rolls a card's markup instead of using `<x-...card>`, you now have two definitions of a card that will drift. The mockup and production must share one component definition.

**Why**: Shared components mean a visual change is made once and both the mockup and production move together. It also makes the mockup a living spec: if the mockup still renders correctly, the components are intact.

**Notes for this stack**:

- Livewire/Volt: keep the component dumb-primitive boundary from Step 2 — the Livewire component holds state and passes it down into presentational components.
- A login/auth surface is assembled here from its `login-*` components on **fixtures** (no live auth). The owning feature epic later binds `wire:model` / validation / the guard onto the *same* markup — it extends this scaffold, it does not re-skin it.
- Resist adding "just one" bespoke element inline. If the mockup shows it, it should be (or become) a component.

**Definition of done**:

- Each production page is assembled from the Step 2 components; no production page re-implements component markup inline.
- Swapping a component's markup in one place visibly updates both its mockup view and its production page.

---

## Step 6 — Stand up parity gates: route-render tests + component smoke tests

**What you do**: Add two layers of automated gate, then reserve a human pass for the rest:

1. **Route-render test** — a feature test that hits every mockup route and asserts a 200 (no 500s, no missing-view/Vite-manifest errors). This proves the mockups still render as the component/stylesheet layers evolve.
2. **Component smoke tests** — render each component with representative props and assert it renders without error. Slot-container components that carry no representative props of their own (a page wrapper, a card shell) are smoke-tested via the **rendered page that assembles them**, not in isolation.
3. **Coverage gate (manifest)** — a test that extracts *every* block-class family from the mockup markup (each `class="..."` token reduced to the part before its BEM `__`/`--` separator) and asserts each is either mapped to a component file **or** explicitly classified in a manifest, with a **reason**. A *new* block added to the mockup later fails the gate until it's classified — so "missing substrate" can't be discovered only by eye. The manifest doubles as living "block → home" documentation. This is the gate that makes G6 *and* G7 enforceable.
4. **Human visual pass** — a person compares the rendered production page against the mockup for true visual parity.

**The coverage-gate reason field is load-bearing — name the owning epic, never a vague framework word.** The manifest's danger cells are the ones classified *"handled elsewhere"*: `out-of-scope`, `framework-native`, `'fortify'`, `'auth'`. These are precisely the classifications that **silently skip extraction**, so they are where a real surface goes to die (G7). A reason of `'fortify'` is a lie-shaped hole — it asserts "no markup to build" about a screen that may well be a full branded surface. Require every such cell to name the **concrete owning artefact**: not `'fortify'` but `'surface built in 01-10 Story 5; mechanism wired in 01-04 Story 1'`. If you can't name where the surface is built and where the mechanism is wired, the cell is unclassified — and the gate should treat it as a failure, not a pass. A reason that names an epic is auditable; a reason that names a framework is an alibi.

**Fidelity-critical rule — the automated gates guard *structural* parity and *coverage*; only a human guards *visual* parity.** A green suite proves pages and components *render* and that every mockup block *has a home*; it does **not** prove they *look like* the mockup. Do not assert pixel/visual equivalence by parsing rendered HTML — that test is brittle and gives false confidence. Keep the division explicit so nobody over-trusts the checkmark. Likewise a **must-NOT** criterion (this surface must *not* be wired to auth / must *not* route through the administrator handoff) can't be self-asserted — write a test that **names the wrong-flow artefact and proves its absence** (no shared action, no `auth` middleware, renders 200 unauthenticated, links to neither the handoff nor the admin redirect).

**Why**: The route-render test is what *holds* fidelity over time — it turns "looks like the mockup" from a one-time check into a regression boundary. The coverage gate is what holds *completeness* — it is the only thing standing between "the foundation looks done" and "the foundation is done". The human pass is honest about the gates' ceiling.

**Notes for this stack**:

- Pest feature tests for route-render; a single data-driven test over the mockup route list is ideal.
- For copy assertions inside these tests, see the Step 7 seams — markup-wrapped and pluralised copy will bite naive `assertSee`.
- Stand the route-render test up **early** (right after Step 1 produces routes), not at the end — it then protects every subsequent step.

**Definition of done**:

- A test asserts 200 for every mockup route; it runs in CI.
- Each component has a render smoke test (slot containers via their assembled page).
- The coverage gate classifies every mockup block-class family (no unclassified families, no stale manifest entries), and every "handled elsewhere" reason **names the owning epic/story**, not a framework word.
- Each must-NOT criterion has an explicit negative test that names the wrong-flow artefact.
- The docs/PR explicitly note that visual parity is verified by a human pass, not by the suite.

> **Worked example (ranking system):** `tests/Feature/MockupRoutingTest.php` proved route-level render parity across the mockup registry; visual parity itself was still confirmed by a human reviewing screenshots.
>
> **Worked example (user registration system):** `tests/Feature/Customer/MockupCoverageGateTest.php` extracts every block-class family and maps it to a component or a reason. The `login-*` families were originally classified `'fortify'` — a vague framework reason that let them ship un-extracted. The fix moved them into the component manifest and changed surviving "handled elsewhere" reasons to name the owning epic. A companion must-NOT test (`serves /login as a standalone surface, not the administrator SSO handoff`) asserts `/login` shares no action with the handoff, carries no `auth` middleware, renders 200 unauthenticated, and links to neither `/auth/sso` nor `/admin/`.

---

## Step 7 — Correction-seams pre-flight checklist

Steps 1–6 make the UI match by construction. What's left is the gap between a *static* mockup and a *live* system — and that gap appears in the **same predictable places every time**. These are not fidelity failures; they are known work. Walk this checklist before calling a screen done.

**Fidelity-critical rule — treat the seams as a pre-flight checklist, not after-the-fact debugging.** Walk every screen against the list *before* declaring it done; never assume the mockup's happy path is the whole system. A seam caught on the checklist is a five-minute decision; the same seam caught at render time (or in review) is a rework cycle.

**1. Invented data — the mockup shows data the schema doesn't have.**
A mockup confidently renders a field that no column backs — it invents data to look complete, and you only discover the gap when you try to wire it.

- **Mitigation**: before promising a field in an acceptance criterion or wiring it on a page, **check the schema for the *specific* column the mockup implies**. If it isn't there, decide deliberately: add the column, derive the value, or drop the field.

**2. Copy wrapped or pluralised in markup defeats naive assertions.**
"X of N" copy wrapped in `<strong>` defeats a single `assertSee('1 of 2 categories recorded')` because the markup splits the string; `Str::plural` makes "1 category" vs "2 categories" count-sensitive.

- **Mitigation**: prefer `assertSeeText` over `assertSee` and **assert on text, not markup** — or split the assertion. Use **count-matched fixtures** so the seeded count matches the pluralised string you assert.

**3. States the mockup never drew.** A mockup shows the happy path. Live screens also have empty, loading, error, zero-result, single-item, and overflow/long-content states the mockup simply didn't draw.

- **Mitigation**: for each screen, **enumerate the missing states** (empty / loading / error / one item / many items / very long strings) and design each.

**4. Inline-Blade directive pitfalls cost a compile cycle.**
`@if(...)...@endif` glued directly onto preceding text and the `@php(...)` paren form both fail to compile.

- **Mitigation**: use a **ternary inside `{{ }}`** for inline conditional text, and a full `@php ... @endphp` block (or a method on the view/component) for logic.

**5. Faked client-side interactions become real server state.** The showcase fakes interactivity with inline JS — `onclick="this.classList.toggle('selected')"`, `showScreen()` for navigation, a fake `onsubmit` that jumps to the next screen. These are *illustrations* of behaviour, not production code.

- **Mitigation**: re-implement each faked interaction as real **Livewire/Volt state** (selection, wizard step, validation), and real navigation (route links, form submits, real auth). For a login surface, the mockup's "Forgot password?" / "Register" links and prefilled credentials are illustrations: wire the real external links (or in-app routes) and clear the prefills. Read the mockup's JS for *intent*, then throw it away.

**6. Email "screens" don't render like web pages.** Screens that are actually Mailables can't rely on the shared stylesheet — email clients need inline styles.

- **Mitigation**: build these as Laravel Mailables with mail-specific Blade/markdown views and **inline-safe styling**; verify them in a mail previewer, not the route-render test.

**7. Mechanism mismatches between the mockup comment and reality (G7's seam).** A mockup comment names a mechanism (`<!-- tokenised request from the administrator SSO -->`, `<!-- handled by SSO -->`) that may not match the spec ADs or prior art — the designer guessed at the wiring.

- **Mitigation**: treat the mockup as visual-only. Reconcile the mechanism against the **spec architecture decisions + prior art**, and confirm with the human if still ambiguous. Build the surface regardless of the mechanism debate; the surface is real even while the wiring is being settled.

**Definition of done**:

- Every screen has been walked against seams 1–7, with each applicable seam either resolved or explicitly noted as out of scope.
- Data fields are schema-backed (or deliberately dropped); copy assertions use `assertSeeText` + count-matched fixtures; missing states are designed; no `@php(...)`/glued-`@if` remains; faked JS is replaced by Livewire state; emails render inline-styled; mechanism mismatches are reconciled against the spec/prior art.

> **Worked example (ranking system):** seams 1, 2 and 4 are taken verbatim from the parity retro's Codebase Discoveries and Testing Gaps — the missing "Imported by" column, the `<strong>`/`Str::plural` copy-assertion traps, and the `@php(...)`/glued-`@if` compile failures all bit on the real build before becoming pre-flight items here.
>
> **Worked example (user registration system):** seam 7 is the login-surface lesson — the mockup comment said "tokenised request from the administrator SSO" but the prior art and spec AD put customer credentials against an external identity system. The surface was built either way; only the mechanism wiring waited on the reconciliation.

---

## Quick reference (the order is the method)

1. Decompose the mockup into per-screen Blade views at real routes. **Classify each screen's surface; note its mechanism separately (a login page is a surface, not a framework excuse — G7).**
2. Extract repeated markup into presentational components — **classes verbatim**, **whole mockup** (G6 + G7).
3. Lock three sources of truth: tokens, component contracts, copy.
4. Wire the one stylesheet in **globally** (Vite `app.css`) — never page by page.
5. Assemble production pages from the **same** components.
6. Stand up parity gates — route-render + smoke tests guard structure, the coverage gate guards completeness (**every "handled elsewhere" reason names the owning epic, never a framework word**); a human guards visuals.
7. Walk the correction-seams checklist before calling it done.

> **Run Steps 1–4 as Phase 0, before spec→epics** — feature epics should consume a complete, gated component substrate, not discover its gaps mid-build.
>
> **Preconditions that must not be reordered**: extract components from the **whole mockup** (Step 2, G6+G7) — not just the screens Step 1 routed, and not skipping surfaces a framework "handles" — *before* building production pages (Step 5); stand up the route-render gate (Step 6) *early*, right after routes exist (Step 1).
