# Playbook companion: Mockup → Filament Fidelity (theme, not markup)

**Purpose**: the long-form companion to the `mockup-to-filament` SKILL.md — the same
Step-0-guard + six-step method, with worked examples that show *why* Filament fidelity
is a shared **theme** and not shared markup.

**Status — seeded, deliberately lean.** This companion
is seeded from the examples harvested when the original combined fidelity skill was split
into `mockup-to-blade` + `mockup-to-filament`, plus theme/parity retros from a prior
build. It is intentionally **not exhaustive**: expand it after a second real Filament
build proves more patterns. The embedded SKILL.md backbone is sufficient to execute the
method; this file is depth, not a dependency.

**How to read this**: the worked examples below are evidence the method has run for real.
Paths in **ranking system** / **user registration system** boxes live in those prior builds; they are
citations, not files to open from another project.

---

## Worked example — the `viteTheme` collapse (Step 3: theme once)

> **ranking system.** Registering the mockup's stylesheet as a Filament theme via
> `->viteTheme('resources/css/filament/<theme>.css')` in `AdminPanelProvider` styled
> **three custom admin pages at once** and **collapsed four downstream "convert this page"
> stories** into a single theme registration. This is the Filament fidelity principle in
> practice: the mockup's *look* arrived through one theme wiring, not by copying its markup
> into each page. What looked like N per-page conversion tasks became "wire the theme once,
> then verify". (Documented in that build's `docs/retros/01-retro-dashboard-filament-conversion-and-parity.md`.)

**Lesson**: the theme is the highest-leverage move on a Filament surface. One correct
`viteTheme` registration, pulling the mockup's token layer, satisfies the styling of every
native construct at once.

## Worked example — parity resolved once, not page by page (Step 3 + AD5 Phase 0)

> **user registration system (`01-11`).** The theme + parity-delta work was effectively the Phase 0 of
> this method **done late** — after feature pages had already landed. Each feature page had
> re-discovered the same gaps between Filament's native `fi-*` rendering and the mockup
> (density, spacing, chrome), and parity was chased page by page instead of owned once. The
> fix consolidated the deltas into the theme story: theme CSS targeting `fi-*` selectors,
> resolved **once**, ahead of the feature work.

**Lesson**: run theme + parity as Phase 0 (AD5). Parity deltas are a *theme* concern; a
delta patched on an individual page is the reactive tail this method exists to kill.

## Worked example — smoke-testing the rendered surface (Step 5: coverage gate)

> **ranking system.** A component smoke test rendered each custom surface with representative
> data and asserted it rendered without error, alongside the route/page render checks. On a
> Filament surface the analogue is: smoke-test the **native constructs and `mk-custom`
> Livewire surfaces as Filament renders them** (Resource pages, custom Pages, mounted Livewire
> in chrome) — never a hand-extracted `fi-*` component in isolation, because there is no
> hand-extracted `fi-*` component. The coverage gate then reads the `mk-*`/`fi-*` tags and
> asserts every tagged block has a classified home.

**Lesson**: gate *coverage* (every tagged block classified — native construct or mounted
Livewire) and *render* (the surface renders under Filament); reserve *visual* parity for a
human. Do not assert visual equivalence by parsing HTML.

---

## Not yet seeded (expand after the next Filament build)

- A full worked classification pass (`fi-*` → construct, `.mk-custom` → Livewire) on a real
  mockup.
- The `filtersForm`/native-filter mapping for a real live-filter screen (Step 2).
- A concrete `mk-custom` Livewire-in-chrome surface end to end (Step 4).
