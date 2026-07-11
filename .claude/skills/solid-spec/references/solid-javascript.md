# SOLID heuristics — JavaScript (and JS embedded in HTML / Blade)

JavaScript SOLID smells, including the special case of `<script>` blocks living inside `.html` and `.blade.php` files (and Alpine `x-data` blocks, inline event handlers, and `wire:click` payloads that contain JS).

The key thing to remember when sweeping templates: **JS-in-template is the worst SOLID hot spot in most projects** because it's "just glue" so it accumulates without review. Apply the same principles you'd apply to a `.js` module.

## Where to find JS that needs scanning

- `*.js`, `*.mjs`, `*.ts`, `*.tsx`, `*.vue` — obvious.
- `<script>` blocks inside `*.html`, `*.blade.php`, `*.vue` (the `<script>` block, separately from the `<template>`).
- Inline event handlers: `onclick="…"`, `onchange="…"`, `oninput="…"` etc. Each is a JS expression.
- Alpine.js: `x-data="{ … }"`, `x-init="…"`, `x-on:click="…"` — Alpine evaluates these as JS.
- Livewire: `wire:click="$dispatch('event', { … })"` — the payload object is JS; complex `$dispatch` chains are smells.
- Vite/JS modules referenced from `@vite([...])` directives in Blade.

When a finding lives inside a Blade `<script>` block, the citation is the host file with the line of the script tag (or the line of the offending statement when easily attributable). Use `(<script>#1)` or `(x-data)` as the symbol if there's no real function name.

## Single Responsibility (SRP)

### General JS signals
- A module that imports DOM utilities, an HTTP client, a state library, and analytics is doing too much.
- A function longer than ~40 lines or with > 3 nested blocks.
- React/Vue components managing remote fetching, local state, formatting, and rendering — extract data fetching to a hook/composable, formatting to a pure function.
- A class with `init()`, `bindEvents()`, `loadData()`, `render()`, `destroy()` — that's MVC squished into one file.

### JS-in-template signals (high yield)
- A Blade `<script>` block longer than ~30 lines. The script is now a first-class module without the discoverability or testability of one. Recommendation: extract to `resources/js/{feature}.js`, register via `@vite`, and dispatch initialisation via DOM events.
- An Alpine `x-data="{ ... }"` literal that defines > 3 methods or holds business logic (HTTP calls, calculation chains). Recommendation: extract to a named Alpine component (`Alpine.data('feature', () => ({...}))` in `resources/js/alpine/feature.js`).
- An inline `onclick="javascript:doStuff(); andThis(); andAlso()"` chain. Recommendation: bind a single named handler from a JS module via `addEventListener` (or via Livewire/Alpine declaratively).
- A Blade `<script>` block reading server data via Blade interpolation (`const id = {{ $note->id }};`) AND making decisions from it AND mutating the DOM. Three concerns: data, logic, presentation. Pass server data via `data-` attributes or a single JSON payload, then let an extracted module handle logic.

### Recommendation phrasing
- "Extract the `<script>` block in `{file.blade.php}:{line}` to `resources/js/{feature}.js`; register via `@vite(['resources/js/{feature}.js'])`; pass server-side data via `data-` attributes on the host element."
- "Move the inline `x-data` block at `{file.blade.php}:{line}` to a named Alpine component `Alpine.data('{name}', ...)` in `resources/js/alpine/{name}.js` and reference it via `x-data=\"{name}\"`."
- "Split the `{ComponentName}` component in `{file}` into a data hook `use{Domain}` and a presentation component; the hook owns fetch + state, the component owns markup."

## Open / Closed (OCP)

### Signals
- `switch (type)` blocks dispatching to per-type handlers, where adding a type means editing the switch.
- `if/else if (event.target.matches('.foo'))` chains in delegated event handlers.
- Hard-coded type registries inside the consumer (`const renderers = { pdf: renderPdf, csv: renderCsv }`) when the consumer isn't the registry's owner.
- Component prop unions where each branch maps to a different render function inside the same component body.

### JS-in-template signals
- A `<script>` block with `if ($el.dataset.kind === 'a') { ... } else if (=== 'b') { ... }` per-kind branching. Recommendation: register a dispatcher map keyed by `data-kind` and let each kind register its own handler in its own module.
- An Alpine component that branches on a `mode` prop in every method. Recommendation: separate Alpine components per mode (`Alpine.data('formCreate', ...)`, `Alpine.data('formEdit', ...)`) sharing a base via `Alpine.data('formBase', ...)` composition.

### Recommendation phrasing
- "Replace the `switch` in `{file}:{line}` with a dispatch map; each handler lives in its own module under `{dir}/handlers/{kind}.js` and registers itself via the map."
- "Replace the `if/else if` chain in the delegated handler at `{file}:{line}` with `event.target.closest('[data-action]')?.dataset.action` lookup against a handler map."

## Liskov Substitution (LSP)

LSP violations are rarer in JS because there's less classical inheritance, but they show up in:

### Signals
- Subclasses (in class-based components or inheritance hierarchies) overriding methods to throw or no-op.
- React/Vue child components that "look like" the parent contract (same props) but silently ignore some props or coerce types.
- Polymorphic functions where one variant returns `undefined` for inputs the others handle — callers learn to special-case.
- Adapter classes wrapping a third-party SDK that don't honour the base interface (e.g. a logger adapter that drops `debug` calls but the base claims to log).

### Recommendation phrasing
- "Replace the `throw new Error('not supported')` branch in `{Subclass}.{method}` at `{file}:{line}` by removing the inheritance and composing the shared behaviour explicitly."
- "Add the missing return path in `{function}` at `{file}:{line}` so all branches return the same shape (currently `undefined` for kind X, `{ data, meta }` otherwise)."

## Interface Segregation (ISP)

### Signals
- A "base component" that exposes 12 props of which most consumers use 3.
- Hooks (`useEverything()`) that return a kitchen-sink object — consumers destructure the same 2 fields each.
- TypeScript interfaces with optional fields where most consumers access only one variant — the interface is two interfaces in a trench coat.
- An event bus with one event type carrying a discriminated union — subscribers all do `if (event.kind === 'mine') return; ...` at the top.

### Recommendation phrasing
- "Split `{Hook}` in `{file}:{line}` into `use{Read}` and `use{Mutate}`; current consumers either read or mutate, never both."
- "Replace the discriminated event in `{file}:{line}` with per-kind events; subscribers subscribe to only what they care about."

## Dependency Inversion (DIP)

### Signals
- `import` statements pulling in concrete modules (HTTP client, analytics, storage) at the top of business logic; the consumer can't be unit-tested without stubbing the module.
- `fetch(...)` / `axios(...)` calls inside components or hooks.
- Direct `localStorage` / `sessionStorage` / `window.*` access deep in business logic.
- Module-level mutable state imported across files.

### JS-in-template signals
- A `<script>` block that does `fetch('/api/foo').then(...)` directly. Recommendation: extract to a typed client module (`resources/js/api/{domain}.js`) with a single export per endpoint; the script imports that and is unaware of HTTP concerns.
- An Alpine component that calls `Livewire.dispatch(...)` AND `fetch(...)` AND `localStorage.setItem(...)` — three external dependencies, no abstraction. Recommendation: inject a single "service" object via `x-data="myComponent({ api, storage })"` where `myComponent` is defined in JS and tested separately.

### Recommendation phrasing
- "Replace the inline `fetch('/api/...')` in `{file}:{line}` with `import { {endpoint} } from '@/api/{domain}'`; the API module wraps fetch with error handling and is the only place that knows about the URL shape."
- "Inject the storage abstraction into `{ComponentName}` instead of calling `localStorage` directly; the component receives `storage` as a prop / via context."

## Cross-cutting JS-in-template signals

These are template-specific anti-patterns worth flagging even if they don't map cleanly to one principle:

- **`@push('scripts')` blocks** in Blade containing complex JS — the same SRP smell as a `<script>` block; extract.
- **Server-rendered config blobs** in JSON via `<script type="application/json" id="config">{!! $config->toJson() !!}</script>` — fine, but if multiple scripts read this AND mutate it, the blob has become module state. Wrap reads in a small accessor module.
- **`@vite([...])` listing one-off page scripts**: fine for a few, but a directory like `resources/js/pages/{many-files}.js` each with its own ad-hoc bootstrap is a sign the scripts should consolidate around a router/dispatcher.
- **Livewire `$dispatch` payloads carrying logic** (`$dispatch('save', { id, processed: doMath() })`) — the math belongs in the component method that builds the dispatch.
- **Inline event handlers as a JS dump** (`<button onclick="loadX(); thenY(); thenZ()">`) — extract to a named handler.

## What is NOT a SOLID violation

- **A short Alpine `x-data` block** with one or two methods is fine — Alpine is *for* small inline state.
- **A `<script>` block that wires up a single event listener** is fine; it's literally the bootstrap.
- **A `fetch` call in a one-shot debug page** doesn't need an API client abstraction.
- **A React component with many props** is fine if those props all describe the same conceptual thing (e.g. a `<DataTable>` with column config, row data, pagination, sort, etc.). Don't conflate "many props" with "many responsibilities".
- **Importing a logger or analytics SDK at the top of a UI module** is fine in practice — these are framework-level concerns and the substitution cost rarely materialises.
- **Passing a Livewire model id from Blade to JS via `data-` attribute** is the right pattern; don't recommend wrapping it in a JSON blob unless multiple data points need to travel together.
