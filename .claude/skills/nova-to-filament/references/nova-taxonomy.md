# Nova primitive taxonomy

This is the **fixed checklist** the audit walks. Completeness is by construction: every category below
must be visited and must report a count in the audit — *including a count of zero*. A category is never
skipped because "the app probably doesn't use it"; absence is a finding worth recording, not a reason to
look away.

Each category lists: **where it lives**, **how to detect it** (the static signals — base class, path,
call-site grep), and **what to capture** (the behavioural attributes that must survive the migration, not
just the fact that the primitive exists). The "what to capture" column is what makes the audit an
*implementation-depth* audit rather than a file census.

> **Detection is signal, not proof.** Grep/path signals find the common case. Dynamic registration,
> traits, package-supplied primitives, and `->merge()`d field sets can hide instances — see
> *Cross-cutting: things that hide* at the end. Anything the static pass cannot confirm becomes a
> `needs-human` item downstream, never a silent omission.

## 1. Resources

- **Where**: `app/Nova/*.php` (conventionally; the directory is configurable).
- **Detect**: classes extending `Laravel\Nova\Resource` (Nova's base resource). The `$model` property
  binds the Eloquent model. `NovaServiceProvider::resources()` may register additional ones explicitly.
- **Capture**: `$model`; `$title`, `$search` (searchable columns), `$with` (eager loads); `$group`
  (sidebar grouping); `$perPageOptions`, `$polling`, `$clickAction`; soft-delete behaviour;
  `indexQuery` / `detailQuery` / `relatableQuery` scope overrides; `redirectAfterCreate/Update`;
  `subtitle()`; per-view field visibility; authorization methods (see §10); global-search participation
  (`$globallySearchable`); `$displayInNavigation`.

## 2. Fields

- **Where**: inside each resource's `fields(Request $request)` (and `fieldsForIndex/Detail/Create/Update`
  when present), lens `fields()`, and action `fields()`.
- **Detect**: `Laravel\Nova\Fields\*` `::make(...)` calls. The built-in field types include (non-exhaustive,
  audit must capture whatever is actually used): `ID`, `Text`, `Textarea`, `Markdown`, `Trix`, `Code`,
  `Number`, `Currency`, `Email`, `Password`, `PasswordConfirmation`, `Boolean`, `BooleanGroup`, `Select`,
  `Date`, `DateTime`, `Timezone`, `Country`, `Color`, `Heading`, `Hidden`, `Slug`, `Status`, `Badge`,
  `Gravatar`, `Avatar`, `Image`, `File`, `Audio`, `VaporFile`, `VaporImage`, `KeyValue`, `Tag`, `Place`,
  `Sparkline`, `Stack`, `Line`, `URL`, `Number`. **Relationship fields are §9.** Panels/tabs and
  `Stack`/`Line` layout wrappers count too.
- **Capture** (per field — this is where migrations silently lose behaviour):
  - Validation: `rules`, `creationRules`, `updateRules`.
  - Value transforms: `resolveUsing`, `displayUsing`, `fillUsing`, `fillCallback`, computed fields
    (a field made from a closure/attribute rather than a column).
  - Visibility & state: `onlyOnIndex/Detail/Forms`, `exceptOnForms`, `hideWhenCreating/Updating`,
    `showOnIndex/Detail`, `readonly`, `nullable`, `sortable`, `filterable`, `copyable`, `dependsOn`
    (conditional fields), `default`.
  - Presentation: `help`, `placeholder`, `stacked`, `textAlign`, `displayUsing` formatting,
    `enum`/`options` maps for `Select`/`Badge`/`Status`.
  - Authorization: `canSee` closures.
  - **Custom field types** (bespoke Vue components extending `Laravel\Nova\Fields\Field`) — flag
    separately; these are almost always `[rebuild]`, not a mapping.

## 3. Actions

- **Where**: `app/Nova/Actions/*.php`; referenced from a resource's `actions(Request $request)`.
- **Detect**: classes extending `Laravel\Nova\Actions\Action` or `DestructiveAction`; inline actions
  (`Action::using(...)`), and `ShouldQueue` actions.
- **Capture**: `handle(ActionFields $fields, Collection $models)` logic; the action's `fields()`
  (action-form inputs); `$destructive`, `$standalone`, `$sole`; queued vs sync (`implements ShouldQueue`,
  `$connection`, `$queue`); confirmation text/button labels; `canSee`/`canRun` authorization;
  pivot actions; chunk/`$chunkCount`; response type (message, download, redirect, openInNewTab,
  Nova modal).

## 4. Filters

- **Where**: `app/Nova/Filters/*.php`; referenced from a resource/lens `filters()`.
- **Detect**: classes extending `Laravel\Nova\Filters\Filter`, `BooleanFilter`, or `DateFilter`.
- **Capture**: `apply($request, $query, $value)` query logic; `options()` (the selectable values);
  default value; whether it is a boolean/date/select filter (drives the Filament equivalent).

## 5. Lenses

- **Where**: `app/Nova/Lenses/*.php`; referenced from a resource's `lenses()`.
- **Detect**: classes extending `Laravel\Nova\Lenses\Lens`.
- **Capture**: `query(LensRequest $request, $query)` (the custom aggregate/filtered query — often the
  whole point of the lens); `fields()`; `actions()`; `filters()`; `cards()`. A lens is effectively a
  bespoke alternate index and frequently maps to a filtered/aggregated Filament table or a custom page.

## 6. Metrics

Nova has four metric types — **audit each as its own sub-count**, because they map to different Filament
widgets:

- **Value** — `Laravel\Nova\Metrics\Value`; `calculate()` returns a single value with comparison to a
  prior range. Capture ranges, `format`, `prefix/suffix`, the aggregate (count/sum/avg/max/min).
- **Trend** — `Laravel\Nova\Metrics\Trend`; time-series over a range/unit. Capture unit, range, aggregate.
- **Partition** — `Laravel\Nova\Metrics\Partition`; grouped breakdown. Capture the group-by and labels.
- **Progress** — `Laravel\Nova\Metrics\Progress`; progress toward a target. Capture the target and
  current calculation.
- **Where**: `app/Nova/Metrics/*.php`; referenced from `cards()` on resources/dashboards.
- **Capture (all)**: the `calculate()` query, `ranges()`, `cacheFor()`, `uriKey`, and where the metric is
  surfaced (which dashboard/resource cards list).

## 7. Dashboards

- **Where**: `app/Nova/Dashboards/*.php`; the default `Main` dashboard; registered in
  `NovaServiceProvider::dashboards()`.
- **Detect**: classes extending `Laravel\Nova\Dashboards\Dashboard` (or `Main`).
- **Capture**: `cards()` composition (which metrics/cards, in what order); `label`, `uriKey`;
  authorization (`canSee`).

## 8. Cards

- **Where**: `app/Nova/*` cards; referenced from `cards()` on resources and dashboards.
- **Detect**: classes extending `Laravel\Nova\Card` that are **not** metrics (metrics are §6). Includes
  Nova's `Help` card and bespoke Vue-backed cards.
- **Capture**: what the card renders; whether it is a built-in, a metric (cross-reference §6), or a
  **custom Vue card** — the latter is typically `[rebuild]`.

## 9. Relationships (relationship fields)

Relationship fields are audited as their own category because they carry migration-specific behaviour
(pivot data, relatable queries) beyond plain fields:

- **Detect**: `BelongsTo`, `HasOne`, `HasOneThrough`, `HasMany`, `HasManyThrough`, `BelongsToMany`,
  `MorphOne`, `MorphMany`, `MorphTo`, `MorphToMany`, `MorphedByMany` (`Laravel\Nova\Fields\*`).
- **Capture**: the related resource; pivot fields (`->fields(...)` on many-to-many); `relatableQuery`
  scoping; searchable/withSubtitles; `dependsOn` for polymorphic (`MorphTo` `->types([...])`);
  `singularLabel`; inline creation; whether shown as a relationship panel or a select.

## 10. Policies / Authorization

- **Where**: Laravel policies in `app/Policies/*.php`; plus Nova-specific authorization on resources,
  fields, actions, lenses, and the Nova gate.
- **Detect**: policy classes mapped to models (`AuthServiceProvider`); resource authorization methods
  (`authorizedToViewAny`, `authorizedToView`, `authorizedToCreate`, `authorizedToUpdate`,
  `authorizedToDelete`, `authorizedToReplicate`, `authorizedToRestore`, `authorizedToForceDelete`);
  `canSee`/`canRun` closures on fields/actions/cards; the `viewNova` gate in `NovaServiceProvider::gate()`.
- **Capture**: each authorization rule's *logic* — who can do what. Filament uses its own policy
  integration + `->visible()/->authorize()`; the audit must record the rule so it is re-expressed, not
  dropped.

## 11. Custom Tools

- **Where**: registered in `NovaServiceProvider::tools()`; tool classes extending `Laravel\Nova\Tool`;
  resource tools extending `Laravel\Nova\ResourceTool`.
- **Detect**: `Tool`/`ResourceTool` subclasses; their `boot()` registering Vue components; a
  `resources/js` + `nova.mix.js`/`webpack.mix.js` build for the tool's frontend.
- **Capture**: what the tool does end-to-end (routes, Vue components, backend controllers/endpoints).
  Custom Tools are Vue applications with no Filament equivalent — they are `[rebuild]` by default and are
  the highest-risk items in any migration. Capture enough that a rebuild story can be scoped.

## Cross-cutting registration & chrome (audit as its own category)

Beyond the numbered primitives, capture the Nova app's *wiring and chrome*, since these have Filament
equivalents that must be reproduced:

- **`NovaServiceProvider`** (`app/Providers/NovaServiceProvider.php`): `resources()`, `cards()`,
  `dashboards()`, `tools()`, `gate()`, `Nova::serving()` hooks, footer, initial path, `resolveUserUsing`.
- **Main menu / user menu**: `Nova::mainMenu(...)`, `Nova::userMenu(...)`, `MenuSection`, `MenuGroup`,
  `MenuItem` — custom navigation that Filament expresses via navigation groups/items.
- **`config/nova.php`**: brand, path, guard, middleware, `pagination`, currency, timezone,
  storage disk defaults.
- **Nova notifications**, **impersonation**, **action events / action log**, **localisation** of Nova UI,
  and **field macros** (`Field::macro(...)`) — each is a behaviour to reproduce or a `needs-human` call.

## Cross-cutting: things that hide

The static pass can miss instances registered or composed indirectly. The audit must actively look for
these and, where it cannot confirm coverage, record `needs-human`:

- **Dynamic registration** — `Nova::resources()` / `->tools()` fed by a loop, config, or discovery.
- **Traits & base classes** — fields added via a shared trait or an abstract resource `fields()` that
  concrete resources call/merge.
- **`->merge()` / spread field sets** — field arrays composed from helpers rather than listed inline.
- **Package-supplied primitives** — third-party `nova-*` packages contributing fields, cards, tools, or
  actions; check `composer.json`/`composer.lock` for `*/nova-*` packages and treat their contributed
  primitives as first-class audit items.
- **Field/resource macros** — `Field::macro`, `Nova::` macros defined in a service provider.
