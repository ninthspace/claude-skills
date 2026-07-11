# SOLID heuristics — PHP and Laravel

Concrete signals to look for when sweeping PHP code (and the Laravel idioms layered on top). Each section pairs a smell with the *scoped* refactor recommendation the spec should cite — the no-rewrites rule still applies.

## Tooling integration (self-discovered)

When `mcp__laravel-boost__*` tools are available, they sharpen specific heuristics in this file. The mappings below are *optional* shortcuts — every heuristic is also detectable with plain `Grep` / `Read`.

- **`application-info`** — call once at the top of a Laravel sweep so that recommendations match the framework version. Don't suggest a Laravel 12-only API on a Laravel 10 project.
- **`database-schema {table}`** — call before any finding involving `$fillable`, casts, accessors/mutators, scopes filtering on columns, or migrations the refactor implies. The spec should cite the actual column shape, not what `Note::$casts` *says* it is.
- **`database-query`** — sanity-check assumptions like "is `archived` ever NULL?" before proposing a cast/nullability change. Read-only.
- **`search-docs ['{topic}']`** — call before drafting an Architecture Decision that proposes a Laravel pattern (observer extraction, action class, custom cast, container binding, policy, form-request authorisation, scope, queue/job patterns). The doc lookup gives you the version-correct phrasing and surfaces gotchas the heuristic alone won't catch.
- **`last-error` / `read-log-entries` / `browser-logs`** — when the user invokes the skill in response to an active issue, these often point at the smell directly (a stack trace through 14 frames inside one class is a god-class diagnosis with citation already attached).
- **`tinker`** — read-only call-site probing when grep returns ambiguous matches.

The spec body itself is code-citation-driven; tool output is internal evidence, not deliverable content.

## Single Responsibility (SRP)

### Plain PHP signals
- A class file longer than ~400 lines is a strong hint, not proof. Check whether the methods divide into clear groups (data, persistence, presentation).
- Multiple `use` statements pulling unrelated subsystems (`Mailer`, `PDF`, `Logger`, `Storage` in one class) — the class is coordinating too many concerns.
- Method count > ~15 public methods on a non-aggregate.
- Helper methods named after the *operation* the class triggers elsewhere (`sendNotificationFor()`, `recalculateTaxFor()`) — strong sign that the operation belongs in its own class.

### Laravel-specific signals
- **Fat controllers**: any controller method longer than ~30 lines, or a controller class with > 7 public actions. Validation + business logic + persistence + response shaping all in the action body. Recommendation: `FormRequest` for validation, `Action`/`Service` for the business logic, API resource for response shaping.
- **God models**: Eloquent models with `booted()` blocks doing fan-out, multiple private static helpers used only internally, computed-column logic, file-cleanup logic. Recommendation: extract lifecycle hooks to an Observer (`App\Observers\{Model}Observer`), extract reusable operations into `App\Services\{Domain}\` or `App\Actions\`. The model should end up data-shape + relationships + semantic predicates. *Boost: call `database-schema {table}` before recommending changes that touch `$fillable`, `$casts`, or accessor/mutator shapes — the actual columns may diverge from what the model currently declares.*
- **Livewire components doing data + presentation + persistence**: a component that loads, filters, paginates, persists, and re-renders is wearing too many hats. Recommendation: extract query construction to a query builder class or scope, and persistence to an action.
- **Service providers** doing more than wiring: a provider that holds business logic in `boot()` is a smell. Move it to a listener/subscriber.
- **Mailables / Notifications** that build their own data: instead of receiving a prepared DTO, they re-query the DB. Recommendation: pass an immutable DTO into the constructor.

### Recommendation phrasing
- "Extract `{methods}` from `{file}` into `App\Services\{Domain}\{Class}` and inject via constructor at `{caller:line}`."
- "Move `{Model}::booted()` lifecycle to `App\Observers\{Model}Observer`; remove `booted()`."
- "Extract `{controller method}` body into `App\Actions\{Verb}{Noun}` invoked as `($action)($request->validated())`."

## Open / Closed (OCP)

### Signals
- `switch ($type)` or `match ($type)` blocks where each branch instantiates or dispatches by a discriminator. Adding a new type means editing the switch — the class isn't open for extension.
- `if ($x instanceof A) { … } elseif ($x instanceof B) { … }` chains.
- Hard-coded class lookup arrays (`['pdf' => PdfExporter::class, 'csv' => CsvExporter::class]`) that the consumer reaches into — fine if external, smelly if maintained inside the consumer that uses them.
- Enum dispatch tables that grow every time a case is added: a `match($enum)` with a branch per case for the same operation belongs as a method on the enum or in a per-case strategy.

### Laravel-specific
- A controller that switches on a "kind" param to call different services. Recommendation: bind the strategies in a service provider (`$this->app->bind(ExporterStrategy::class, fn ($app, $params) => match ($params['kind']) {...})`) or use a manager pattern (`Illuminate\Support\Manager`).
- Form requests with `rules()` returning conditional rules based on a discriminator — split into typed form requests routed by request kind.

### Recommendation phrasing
- "Replace the `switch` in `{file}:{line}` with a strategy interface `App\Support\{Domain}\{Strategy}Interface`; bind concrete strategies in `AppServiceProvider`."
- "Move `match ($enum)` dispatch in `{file}:{line}` to a method on `{EnumClass}` (PHP 8 enum methods)."
- "Convert the `if/elseif instanceof` chain in `{file}:{line}` to polymorphism — add `{operation}()` to the base contract and let each subclass implement it."

*Boost: when proposing a manager pattern (`Illuminate\Support\Manager`), strategy binding, or contextual binding, run `search-docs ['manager pattern', 'contextual binding', 'service container']` first — the project's Laravel version may have a more idiomatic API (e.g. `scopedIf`, `bindMethod`, `whenHasAttribute`) that the AD should reference.*

## Liskov Substitution (LSP)

### Signals
- Subclass overrides that throw `BadMethodCallException` / `LogicException("not supported")`. The subclass isn't really an `is-a`.
- Subclasses that strengthen preconditions (parent accepts nullable, subclass throws on null).
- Subclasses that weaken postconditions (parent guarantees a non-empty collection, subclass returns empty).
- `assert($x instanceof ChildClass)` immediately after receiving a parent-typed parameter — the consumer doesn't believe the substitution holds.

### Laravel-specific
- Eloquent subclasses (single-table inheritance) overriding scopes or accessors in ways that break parent expectations.
- A subclass policy that denies what the parent allows under the same conditions — usually means the policy hierarchy was the wrong shape; lift the discriminator to the gate.

### Recommendation phrasing
- "Replace inheritance from `{Parent}` in `{Child}` with composition — extract the shared behaviour into `{TraitOrCollaborator}` and let both classes consume it."
- "Move the `throw new BadMethodCallException` branch in `{Child}::{method}` to the call site by checking the type before dispatch; this makes the LSP violation explicit at the boundary."

## Interface Segregation (ISP)

### Signals
- An interface with > ~5 methods that consumers implement partially (with several `throw new BadMethodCallException`).
- Multiple unrelated callers depending on the same fat interface, each using a different subset.
- A `*RepositoryInterface` with `find`, `save`, `delete`, `paginate`, `search`, `aggregate`, `softDelete` — read-only consumers shouldn't need write methods in their type hint.

### Laravel-specific
- `User` model implementing `Authenticatable`, `Authorizable`, `CanResetPassword`, `MustVerifyEmail`, `HasApiTokens` — *this is fine* (the framework's interfaces are intentionally narrow). The smell appears in *project-defined* fat interfaces, not in framework contracts.
- Custom `Contract` interfaces in `app/Contracts/` that grow as new features need a hook — split them.

### Recommendation phrasing
- "Split `{FatInterface}` into `{ReadInterface}` (`find`, `paginate`, `search`) and `{WriteInterface}` (`save`, `delete`); consumers depend on the narrowest contract they need."
- "Drop the `BadMethodCallException` stub for `{method}` in `{Implementation}` by removing the method from `{Interface}` — no caller of this interface uses it."

## Dependency Inversion (DIP)

### Signals
- `new ConcreteClass(...)` calls deep inside business logic for collaborators that have side effects (HTTP clients, database writers, queue dispatchers).
- Static facade calls (`Storage::put()`, `Mail::send()`, `Cache::remember()`) inside testable business logic — the consumer can't substitute a fake without intercepting the facade globally.
- Direct `\DB::table()` or `\DB::statement()` calls inside models or services — bypasses Eloquent, harder to test.
- `App::make()` / `app()` called inside method bodies instead of constructor injection.
- `env()` called outside config files — tightly couples runtime values into business logic and makes config caching dangerous.

### Laravel-specific
- Jobs that resolve dependencies in `handle()` via the service container instead of constructor injection (handles work both ways, but constructor injection is the default; container resolution in `handle()` should be deliberate, e.g. for non-serialisable deps).
- Event listeners doing the work directly instead of dispatching to an action class — bundles "what fires when X happens" with "how X is processed".
- Test code that uses `Storage::fake()` because production code calls `Storage::` directly — fine for adapters, smelly when it forces every consumer to know about faking.

### Recommendation phrasing
- "Replace `new {ConcreteClass}(...)` in `{file}:{line}` with constructor injection of `{Interface}`; bind the concrete in `AppServiceProvider`."
- "Replace `Storage::put(...)` in `{file}:{line}` with injected `Filesystem $disk` (typed via `Illuminate\Contracts\Filesystem\Filesystem`); resolve the disk at the boundary, not in the business logic."
- "Move `env('SOMETHING')` from `{file}:{line}` to `config/{group}.php` and inject the config value via constructor."

*Boost: when the recommendation introduces a config key, run `database-query` (or read `config/`) to confirm no existing key already covers the value — refactoring a hard-coded value into a duplicate config entry is worse than the original. `search-docs ['service container binding', 'contextual binding']` to phrase the binding using the project's idiomatic API.*

## Cross-cutting Laravel signals worth flagging

These don't map cleanly to one principle but show up repeatedly during sweeps:

- **`booted()` lifecycle in models** — observer extraction (SRP).
- **Raw `where('foo', $bar)` in Livewire components** when a query scope exists for the same condition — wires the abstraction (DIP-adjacent).
- **`User::find($id)` in business logic** instead of receiving the User from the boundary — couples the layer to the auth context (DIP).
- **Global `auth()->user()` calls inside services** — the service should receive the user; auth resolution belongs at the controller / Livewire boundary.
- **Form requests doing authorization** alongside validation — fine if the rule is "owns this resource", smelly if it's a 30-line policy. Extract to a Policy.
- **Test doubles built by reflection** because the real class won't construct without a DB — strong DIP signal; the production class is doing too much in its constructor.

## What is NOT a SOLID violation

Worth knowing so the sweep doesn't surface noise:

- **A long Eloquent model with many relationships and scopes** is fine if the methods are all data-shape + semantic predicates. Length alone isn't a smell.
- **A controller that delegates to one action and returns the response** is fine even if it has 10 actions — they're routes, not behaviour.
- **A facade in a one-line adapter** (e.g. a Mailable building its `Envelope`) is fine — facades are framework boundaries.
- **Using `app()->make()` inside a queued job's `handle()`** for a non-serialisable dependency is the documented Laravel pattern. Not a DIP violation.
- **A trait used by one class** is fine if the user is staging an extraction. Don't flag it as "useless trait" without checking commit history.
