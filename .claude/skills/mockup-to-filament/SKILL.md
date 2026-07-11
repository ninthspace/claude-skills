---
name: mockup-to-filament
description: "Convention: `…-mockup(s)` produces a mockup; `mockup-to-…` consumes a mockup, builds the named target. Siblings: `brief-to-mockups` and `filament-mockup` produce mockups; `mockup-to-blade` is the bespoke build lane. Use this skill when turning an HTML mockup into a production Filament admin/CRUD UI and you want fidelity to the mockup's design without hand-copying its markup. Trigger when converting a mockup/showcase for an admin panel, back-office, resource/CRUD screen or dashboard into Filament resources, pages and a Filament theme; when a delivered .html mockup targets a Filament surface; when asked to 'build the admin to match the mockup' on Filament. Covers: classifying screens to native Filament constructs before any extraction, scaffolding resources/pages per FR→screen (live filters = native filtersForm), theming once + resolving parity deltas inside the theme story, mounting bespoke Livewire inside Filament chrome for mk-custom surfaces, a coverage gate that reads the mk-*/fi-* tag vocabulary, and the Filament correction seams. Fidelity here = a shared THEME, not shared markup: the mockup Blade is throwaway and Filament regenerates the markup; fi-* components are configured/themed, never hand-extracted. Do not use for bespoke Blade/Livewire surfaces (those go to mockup-to-blade), for greenfield UI with no mockup, or for non-Laravel stacks."
license: MIT
metadata:
  author: ninthspace
  version: "1.0"
  companion: references/mockup-to-filament-fidelity.md
---

# Mockup → Filament Fidelity

Execute this method to turn an HTML mockup that targets a **Filament** surface (admin panel, back-office, resource/CRUD, dashboard) into a production Filament UI that is faithful to the mockup's *design* **by construction** — through a shared **theme**, not shared markup. This skill is self-contained: the full step backbone is embedded below. The deep companion with worked examples is bundled at `references/mockup-to-filament-fidelity.md`.

## How to use this skill

Work the steps **in order**, starting with the **Step 0 substrate guard**. Each step has a **Rule** (the fidelity-critical constraint) and a **Done when** gate. Do not advance past a step until its gate is met.

## The Filament fidelity principle (read once before starting)

**On a Filament surface, fidelity is a shared *theme*, not shared markup.** This is the deliberate inversion of the bespoke rule (`mockup-to-blade`'s "one shared component, classes verbatim"). Here:

- **The mockup Blade is throwaway.** It settles the *design* — palette, density, spacing, which screens exist — and then it is discarded. You do not lift its markup into production.
- **Filament regenerates the markup.** Native constructs (Resources, Pages, Widgets, Actions) render Filament's own `fi-*` grammar; your job is to *configure* them and *theme* them to match the mockup, never to reproduce their markup by hand.
- **`fi-*` families are configured/themed, never hand-extracted.** Extracting a `fi-*` block into a Blade component forks Filament's grammar and reintroduces drift on the next Filament release — the precise failure this lane exists to prevent.

The mockup is still the **visual** authority (the theme must match it) and still **not** the mechanism authority. What changes from the bespoke lane is *how* the visual is honoured: through Filament's theme layer, not through shared component markup.

## Where this sits in the lifecycle (run as Phase 0, before feature epics)

Run the **theme + parity-delta + custom-component substrate as a Phase 0**, before any feature epic is decomposed. Establish the Filament theme (Step 3), resolve the parity deltas against `fi-*` once, and stand up the `mk-custom` Livewire surfaces (Step 4) up front — then feature epics build resources/pages against a *themed, parity-resolved* panel they simply consume.

The expensive alternative — the one to avoid — is theming and resolving parity **reactively, page by page** as feature epics land. That is the drift tail that a late foundation produces: each feature page re-discovers the same theme gaps, and parity is chased instead of owned. (In one prior build, the theme/parity work was effectively this Phase 0 done late.) Do the theme and parity once, first.

## The step backbone (embedded — this is the source of truth for execution)

### Step 0 — Confirm the substrate is Filament  *(refuse-to-run guard — entry step)*

`mockup-to-filament` builds **Filament** surfaces only — admin panels, back-office, resource/CRUD, dashboards — where fidelity is a shared *theme* and Filament regenerates the markup. Bespoke Blade/Livewire surfaces (fidelity = shared markup, classes verbatim) belong to **`mockup-to-blade`**. This guard is a **hard stop, not an internal branch**.

Do: read the routing table at `docs/mockups/surface-routing.md` (schema `surface · FRs · substrate · producer · builder`) and check its `substrate` column for the surface(s) you are about to build.
- **`substrate = filament`** → proceed to Step 1.
- **`substrate = bespoke`** → **stop and hand off to `mockup-to-blade`.** Do not scaffold Filament constructs for that surface.
- **No routing table exists** → **proceed as Filament.** A missing table is the legitimate **all-Filament-brief path**: `brief-to-mockups` was skipped and `filament-mockup` is the sole producer (it emits the routing rows on its own path). A missing table must **never** hard-fail this builder.

This is the deliberate **inverse** of `mockup-to-blade`'s conservative guard, which asks/stops on a missing table. Between the pair, the bespoke lane treats silence as "ask", the Filament lane treats silence as "proceed" — so neither builder dead-ends on an absent artifact, yet each still refuses the *wrong* substrate when the table names it.

**Rule**: run only on a `filament` row (or an absent table); hand any `bespoke` row to `mockup-to-blade`; never hard-fail on a missing table.

**Done when**: the surface is confirmed `filament` via the routing table, *or* the table is absent (all-Filament path → proceed as Filament); any `bespoke` row has been handed to `mockup-to-blade`.

### Step 1 — Classify every screen against the Filament grammar *before* extracting anything

A delivered mockup is the usual single-file showcase (screens behind a JS switcher). On a Filament surface you do **not** decompose it into hand-authored views — you first **classify** each `#screen-*` against Filament's native grammar, using the mockup's tag vocabulary as the oracle (`filament-mockup`'s `references/fi-grammar.md` / the `.mk-custom` marker it emits — both live in the `filament-mockup` skill, not this one):

- **`fi-*`-tagged blocks** → a **native Filament construct**: a Resource (table + form), a custom Page, a Widget/dashboard, an Action/modal, a Notification. These are *configured*, and Filament owns their markup.
- **`.mk-custom`-tagged blocks** → a **bespoke surface** with no native equivalent → a Livewire component mounted inside Filament chrome (Step 4).

**Rule — classify before you build, and NEVER hand-extract a `fi-*` block.** A `fi-*` family is Filament's *own* markup; copying its classes into a Blade component forks Filament's grammar and guarantees drift the instant Filament updates. If a block is `fi-*` you configure the native construct and let Filament regenerate the markup — you do not lift its classes into a component. Hand-extraction of `fi-*` is prohibited; it is the exact bespoke-lane instinct this substrate inverts.

**Done when**: every screen is mapped to a native Filament construct or an `mk-custom` surface; no `fi-*` block is slated for hand-extraction.

### Step 2 — Scaffold the native constructs, one per FR→screen

For each classified screen, scaffold the **native** Filament construct that backs its FR — a Resource (with its table + form schema), a custom Page, or a Widget — driven by the FR→screen mapping in the routing table, one construct per surface. Build tables/forms with Filament's schema builders; **live filters are the native `filtersForm` / table `filters()`**, search is the native table search, bulk operations are `BulkAction`s.

**Rule — every interactive affordance the mockup shows maps to a native Filament feature, not a reimplementation.** Reimplementing search/filters/actions as hand-rolled Blade/Livewire on a Filament surface is the same contamination as hand-extracting `fi-*`. If Filament renders it natively, configure it — don't rebuild it.

**Done when**: each FR→screen has its native construct scaffolded; live filters/search/actions use native Filament features; the panel renders each surface.

### Step 3 — Theme once, and resolve every parity delta inside the theme story

Fidelity to the mockup's *look* comes from **one Filament theme**, never per-page CSS. Bring the mockup's token layer (palette, font, spacing, radii) into the Filament theme CSS and register it **once** via `->viteTheme(...)`. Where native Filament rendering differs from the mockup (density, spacing, chrome), resolve the **parity deltas *inside the theme*** — theme CSS targeting `fi-*` selectors — **once, in the theme story**, not reactively page by page.

**Rule — parity is a theme concern resolved once; never patch an individual Filament page to match the mockup.** If a page looks wrong, the theme is missing a delta for that `fi-*` construct — fix the theme, not the page. Page-by-page patching is the reactive-parity tail this method exists to kill.

**Done when**: one theme registered via `viteTheme`; the token layer matches the mockup; parity deltas live as theme rules against `fi-*` selectors; no per-page style patches.

### Step 4 — Mount `mk-custom` surfaces as Livewire inside Filament chrome

The `mk-custom` screens — the ones with no native equivalent — are the **only** ones you build by hand, as **Livewire components mounted inside Filament chrome** (a custom Filament Page rendering the Livewire component, or a panel-wrapped full-page Livewire route). Within that custom component the bespoke instinct applies, but it still lives inside Filament's chrome and is styled by the same theme.

**Rule — custom means `mk-custom`, and it lives *inside* the panel, not beside it.** Do not let an `mk-custom` surface become an excuse to hand-build things Filament renders natively; keep the custom scope to the genuinely bespoke block, themed by the shared theme.

**Done when**: every `mk-custom` surface is a Livewire component mounted in Filament chrome, styled by the shared theme; nothing native was rebuilt as custom.

### Step 5 — Stand up the coverage gate that reads the tags

Add a coverage gate that reads the mockup's `mk-*`/`fi-*` tag vocabulary and asserts every tagged block has a classified home: each `fi-*` family maps to a configured native construct, each `mk-custom` to a Livewire-in-chrome surface — with a **reason** naming the owning artefact for anything deferred. A newly-tagged block fails the gate until it is classified.

**Rule — the gate proves *coverage* (every block classified); only a human proves *visual* parity.** Do not assert visual equivalence by parsing HTML, and "handled elsewhere" reasons must name the concrete owning epic/story, never a framework word.

**Done when**: the gate classifies every `fi-*`/`mk-custom` family; deferred reasons name the owning artefact; a human visual pass is reserved.

### Step 6 — Walk the Filament correction seams

Before calling a surface done, walk the Filament-specific seams:

1. **Native ≠ mockup chrome** — Filament renders more than the mockup drew (pagination, empty states, action modals, toasts); accept the native affordance rather than forcing the mockup's static shape.
2. **`fi-*` upgrades move the class surface** — theme deltas target Filament's classes; a Filament version bump can shift them. Pin the version and re-verify the theme after upgrades.
3. **State the mockup faked** — a static table becomes real records, sorting, filtering, pagination and authorization; enumerate and wire each via native features.
4. **`mk-custom` boundaries** — a custom Livewire surface must still inherit the panel's auth, layout and theme; verify it does not escape the chrome.

**Rule — reconcile to Filament's native behaviour, not the mockup's static drawing.** The mockup is a design target for the *theme*; where live Filament behaviour diverges from a static screen, the live behaviour wins.

**Done when**: each surface is walked against seams 1–4, each resolved or explicitly noted out of scope.

## The bundled companion

`references/mockup-to-filament-fidelity.md` (bundled in this skill directory) is the long-form version — the same method with the *why* spelled out and worked Filament examples. <!-- Seeded in Story 3 (Task 3.1). -->
