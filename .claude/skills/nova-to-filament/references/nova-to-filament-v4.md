# Nova → Filament v4 mapping

The **offline baseline** for mapping each Nova primitive onto Filament v4. It is a starting point, not the
final word: at run time the grounding phase confirms and corrects every mapping against the pinned
Filament version's docs and the target's installed `vendor/filament` source (which win on any conflict).
Treat a row here as a *hypothesis with a default disposition*, to be verified — never as settled truth.

Every category in `references/nova-taxonomy.md` has at least one row here. A primitive with no honest
Filament equivalent is still listed — with disposition `[rebuild]` — so the reference never has a gap.

## Disposition classes

- **direct** — a like-for-like construct exists; the mapping is essentially type-to-type. *A `direct`
  default still becomes `behaviour-change` for a specific instance if the audit flagged customisation on
  it (a callback, computed value, conditional, custom rule).*
- **behaviour-change** — an equivalent exists but the API/shape/behaviour differs enough that the port is
  a re-expression, not a copy (re-written query, different config surface, restructured relationship).
- **[rebuild]** — no equivalent; the functionality must be rebuilt (typically Nova's Vue-based custom code
  → Filament's Livewire/Blade, or a feature Filament core simply doesn't have).

## v4 structural notes (read first — they shift many dispositions)

- **Fields split in two.** A Nova field appears in *both* the index (table) and the forms. Filament
  separates these: **table columns** (`Filament\Tables\Columns\*`) and **form components**
  (`Filament\Forms\Components\*`). One Nova field therefore usually maps to *two* Filament constructs (a
  column + a component). The audit's per-view visibility flags (`onlyOnForms`, `exceptOnForms`, etc.)
  drive which halves are needed.
- **Schemas.** v4 unifies forms and infolists under `Filament\Schemas` (layout components: `Section`,
  `Grid`, `Tabs`, `Fieldset`, `Split`). Nova `Panel`/`Heading`/layout maps here.
- **Actions unified.** v4 consolidates actions under `Filament\Actions\*` (one `Action`/`BulkAction`
  usable across tables, pages, and infolists) — no separate `Tables\Actions` namespace as in v3.
- **Infolists** cover Nova's detail-view read-only rendering; several "detail only" Nova fields map to
  infolist entries rather than form components.

---

## 1. Resources

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `Resource` (`app/Nova`) | `Filament\Resources\Resource` | direct | `$model`, `$title`→`recordTitleAttribute`, `$search`→`getGloballySearchableAttributes()`, `$group`→navigation group. |
| `indexQuery`/`detailQuery`/`relatableQuery` | `modifyQueryUsing` / resource `getEloquentQuery()` | behaviour-change | Scopes re-expressed on table query / resource query. |
| `$with`, `$perPageOptions`, `$polling`, soft deletes | table `->paginated()`, `->poll()`, `TrashedFilter`/`->modifyQueryUsing(withTrashed)` | behaviour-change | Per-resource settings move onto the table/resource config surface. |

## 2. Fields (form components + table columns)

| Nova field | Filament v4 (form / column) | Disposition | Notes |
|---|---|---|---|
| `ID` | (implicit) / `TextColumn` | direct | Filament infers the key; expose via a column if shown. |
| `Text` | `TextInput` / `TextColumn` | direct | `behaviour-change` if `resolveUsing`/`displayUsing`/computed. |
| `Textarea` | `Textarea` / `TextColumn` | direct | |
| `Markdown` | `MarkdownEditor` | direct | |
| `Trix` | `RichEditor` | behaviour-change | Stored-HTML compatibility differs; verify content renders. |
| `Code` | `Textarea` / plugin code field | behaviour-change | No first-class code editor in core; may be `[rebuild]` if language modes needed. |
| `Number` | `TextInput->numeric()` | direct | |
| `Currency` | `TextInput->numeric()->prefix()` / money cast | behaviour-change | Locale/currency handling differs. |
| `Email` | `TextInput->email()` | direct | |
| `Password` | `TextInput->password()` | direct | |
| `PasswordConfirmation` | `TextInput->password()` + `confirmed` rule | behaviour-change | Nova pairs it automatically; Filament wires the rule. |
| `Boolean` | `Toggle` / `IconColumn->boolean()` | direct | |
| `BooleanGroup` | `CheckboxList` / `KeyValue` | behaviour-change | |
| `Select` | `Select` | direct | `options`/`enum` carry over. |
| `Date` | `DatePicker` / `TextColumn->date()` | direct | |
| `DateTime` | `DateTimePicker` / `TextColumn->dateTime()` | direct | |
| `Timezone` | `Select` (timezone list) | behaviour-change | Provide the option list. |
| `Country` | `Select` (country list) | behaviour-change | Provide the option list. |
| `Color` | `ColorPicker` | direct | |
| `Heading` | `Section`/`Placeholder` (schema) | behaviour-change | Layout, not a field. |
| `Hidden` | `Hidden` | direct | |
| `Slug` | `TextInput` + `Str::slug` in `afterStateUpdated` | behaviour-change | Nova's auto-slug-from-field becomes an explicit hook. |
| `Status` | `TextColumn->badge()` + colour map | behaviour-change | |
| `Badge` | `TextColumn->badge()` | behaviour-change | Colour/label maps carry over. |
| `Gravatar` | `ImageColumn` (gravatar URL) | behaviour-change | Compute the URL. |
| `Avatar` | `FileUpload->avatar()` / `ImageColumn->circular()` | behaviour-change | |
| `Image` | `FileUpload->image()` / `ImageColumn` | direct | Disk/visibility config differs. |
| `File` | `FileUpload` | direct | Check disk, path, prunable behaviour. |
| `Audio` | `FileUpload` (accept audio) | behaviour-change | |
| `VaporFile` / `VaporImage` | `FileUpload` on S3/Vapor disk | behaviour-change | Direct-to-S3 flow differs. |
| `KeyValue` | `KeyValue` | direct | |
| `Tag` | `TagsInput` / `Select->multiple()->relationship()` | behaviour-change | Depends on whether tags are a relationship. |
| `Place` | `TextInput` / geocoding plugin | behaviour-change | Nova's Places (Algolia) has no core equivalent — may be `[rebuild]`. |
| `Sparkline` | `ChartColumn` / custom | behaviour-change | |
| `Stack` / `Line` | `Split`/stacked `TextColumn` description | behaviour-change | Composite display re-expressed. |
| `URL` | `TextInput->url()` / `TextColumn->url()` | direct | |
| **Custom field (Vue)** | Custom Filament form component (Livewire/Blade) | **[rebuild]** | Vue → Livewire; no automatic port. |
| Computed field (closure) | `->state(fn)` column / `Placeholder` | behaviour-change | Re-express the closure. |

## 3. Actions

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `Action` (`handle(fields, models)`) | `Filament\Actions\Action` / `BulkAction` (`->action(fn)`) | behaviour-change | `handle(Collection)` logic re-expressed; bulk vs single explicit. |
| Action `fields()` | Action `->form([...])`/`->schema([...])` | behaviour-change | Action form components. |
| `ShouldQueue` action | Dispatch a job from `->action()` | behaviour-change | No queued-action primitive; dispatch explicitly. |
| `$destructive` / confirmation | `->requiresConfirmation()`, `->color('danger')` | direct | |
| `$standalone` | header/standalone action | direct | |
| Inline action | table row action | direct | |

## 4. Filters

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `BooleanFilter` | `TernaryFilter` | behaviour-change | |
| `DateFilter` | `Filter` with a `DatePicker` form | behaviour-change | |
| Select-style `Filter` | `SelectFilter` | direct | `options()`→`options()`. |
| Custom `Filter::apply()` | `Filter->query(fn)` | behaviour-change | Query closure re-expressed. |

## 5. Lenses

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `Lens` (custom `query()`, fields, actions) | Filtered/aggregated table (`modifyQueryUsing`) **or** custom Page + table | behaviour-change / **[rebuild]** | No native "lens". Simple aggregate → a table variant; complex bespoke lens → a custom page rebuild. |

## 6. Metrics

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `Value` metric | `StatsOverviewWidget` (a `Stat`) | behaviour-change | `calculate()` re-expressed; comparison/trend via `Stat` helpers. |
| `Trend` metric | `ChartWidget` (line) | behaviour-change | Range/unit/aggregate rebuilt on the chart data. |
| `Partition` metric | `ChartWidget` (pie/doughnut) | behaviour-change | Group-by rebuilt. |
| `Progress` metric | `Stat` with progress / custom widget | behaviour-change | Target/current re-expressed. |

## 7. Dashboards

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `Dashboard` / `Main` (`cards()`) | `Filament\Pages\Dashboard` + widgets | behaviour-change | Card composition → widget registration/order. |

## 8. Cards

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| Metric card | Widget (see §6) | behaviour-change | |
| Built-in `Help` card | Custom widget / Blade | behaviour-change | |
| **Custom (Vue) card** | Custom widget (Livewire/Blade) | **[rebuild]** | |

## 9. Relationships

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `BelongsTo` | `Select->relationship()` / `BelongsToSelect` | direct | `behaviour-change` with `relatableQuery`/searchable subtitles. |
| `HasOne` | relationship form / infolist entry | behaviour-change | |
| `HasMany` / `HasManyThrough` | `RelationManager` | behaviour-change | Structural: Nova inline panel → a relation manager. |
| `HasOneThrough` | infolist / read-only relation | behaviour-change | |
| `BelongsToMany` (+ pivot fields) | `RelationManager` with pivot / `Select->multiple()->relationship()` | behaviour-change | Pivot fields must be re-declared. |
| `MorphOne` / `MorphMany` | relationship / `RelationManager` | behaviour-change | |
| `MorphTo` (`->types()`) | `MorphToSelect->types()` | behaviour-change | |
| `MorphToMany` / `MorphedByMany` | `RelationManager` | behaviour-change | |

## 10. Policies / Authorization

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| Laravel policy (`app/Policies`) | Laravel policy (honoured natively) | direct | Filament uses the same policy classes. |
| `authorizedToViewAny`/`View`/`Create`/… | Policy methods / `->authorize()` | direct | |
| `canSee` / `canRun` closures | `->visible(fn)` / `->hidden(fn)` / `->authorize()` | behaviour-change | Closures re-expressed on components/actions. |
| `viewNova` gate | Panel access (`canAccessPanel()` on the user) | behaviour-change | |

## 11. Custom Tools

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `Tool` (Vue app + routes) | Custom Filament **Page** / plugin (Livewire/Blade) | **[rebuild]** | Full rebuild; highest-risk migration item. Scope from the audit's captured behaviour. |
| `ResourceTool` (Vue, on detail) | Custom page section / Livewire component in the resource | **[rebuild]** | |

## Cross-cutting registration & chrome

| Nova | Filament v4 | Disposition | Notes |
|---|---|---|---|
| `NovaServiceProvider` (`resources()`/`tools()`/`gate()`) | `PanelProvider` (panel config, `->discoverResources()`) | behaviour-change | Registration model differs. |
| `Nova::mainMenu` / `userMenu`, `MenuSection`/`MenuItem` | Navigation groups/items, `->userMenuItems()` | behaviour-change | |
| `config/nova.php` | Panel configuration | behaviour-change | Brand, path, guard, middleware. |
| Nova notifications | Filament Notifications | behaviour-change | |
| Impersonation | Filament plugin / custom | behaviour-change / **[rebuild]** | Not core. |
| Action events / action log | (no core equivalent) | **[rebuild]** | Nova's `action_events` logging must be rebuilt if needed. |
| `Field::macro` / `Nova::` macros | Filament component macros / custom components | behaviour-change | |
| Localisation of Nova UI | Filament translations / publishing | behaviour-change | |
