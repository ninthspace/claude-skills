# DTO patterns for Laravel

Read this when designing the actual DTO classes. Match the project's existing
conventions first (see SKILL.md step 4); the patterns below are the fallbacks when
the project has no established style.

## Contents
- [Choosing plain PHP vs spatie/laravel-data](#choosing)
- [Plain PHP readonly DTO](#plain)
- [spatie/laravel-data DTO](#spatie)
- [Factory methods](#factories)
- [Collections of DTOs](#collections)
- [Preserving validation](#validation)
- [Where DTOs live](#location)
- [Things that are not DTO candidates](#not-candidates)

<a id="choosing"></a>
## Choosing plain PHP vs spatie/laravel-data

Check `composer.json` for `spatie/laravel-data`. If present, follow it — the team has
already bought into it and mixing styles is worse than either style alone.

Reach for **spatie/laravel-data** when the cluster crosses a boundary that needs
casting, validation, or serialization: request → action, API resource output,
queue payloads, Livewire/Filament form state. You get `::from()`, validation rules,
casts, and `toArray()`/wire serialization for free.

Reach for a **plain `final readonly` class** when the cluster is purely an internal
"these values belong together" grouping with no serialization or validation needs.
It has zero dependencies, is trivially fast, and reads as plain domain vocabulary.

When unsure, start plain. Promoting a plain DTO to a `Data` object later is cheap;
the reverse rarely matters.

<a id="plain"></a>
## Plain PHP readonly DTO

The default. Constructor property promotion + `readonly` gives immutability and a
named-argument-friendly call site in a few lines.

```php
<?php

namespace App\Data;

use DateTimeImmutable;

final readonly class DateRange
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end < $start) {
            throw new \InvalidArgumentException('End must not precede start.');
        }
    }

    public function nights(): int
    {
        return (int) $this->start->diff($this->end)->days;
    }
}
```

Call site before/after:

```php
// before — a data clump repeated across many signatures
$service->quotePrice($venueCode, $startDate, $endDate, $partySize);

// after — the concept is named, and impossible to pass start/end in the wrong order
$service->quotePrice($venueCode, new DateRange($startDate, $endDate), $partySize);
```

Notes:
- `final` unless something genuinely needs to extend it; DTOs are values, not hierarchies.
- Validate *invariants* in the constructor (end-after-start, non-empty), not input
  formatting — that belongs at the boundary (FormRequest / Data rules).
- Add small derived helpers (`nights()`, `isEmpty()`) only when several call sites
  recompute the same thing. Don't add behaviour that mutates or does I/O.

<a id="spatie"></a>
## spatie/laravel-data DTO

Use when the cluster needs to be built from a request/array or serialized out.

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

final class BookingRequestData extends Data
{
    public function __construct(
        public readonly int $userId,
        public readonly DateRangeData $dates,
        public readonly string $venueCode,
        public readonly int $partySize,
        public readonly ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'partySize' => ['required', 'integer', 'min:1'],
            'venueCode' => ['required', 'string', 'exists:venues,code'],
        ];
    }
}
```

`BookingRequestData::from($request)` and `::validateAndCreate($request)` replace a
manual `$request->validated()` unpack. Nested `Data` objects (like `DateRangeData`)
compose naturally. Keep `readonly` on the properties even though `Data` doesn't
require it — immutability is the point.

<a id="factories"></a>
## Factory methods

Give the DTO the construction paths its call sites actually use, so the unpacking
logic lives in one place rather than at every call site.

```php
// from a validated FormRequest
public static function fromRequest(StoreBookingRequest $request): self
{
    return new self(
        userId: $request->user()->id,
        dates: new DateRange(
            new DateTimeImmutable($request->validated('start')),
            new DateTimeImmutable($request->validated('end')),
        ),
        venueCode: $request->validated('venue'),
        partySize: (int) $request->validated('party_size'),
    );
}

// from an Eloquent model, when reconstructing the concept from persisted rows
public static function fromModel(Booking $booking): self { /* ... */ }
```

Prefer named factory methods (`fromRequest`, `fromModel`, `fromArray`) over one
overloaded constructor — the name documents the source and keeps the constructor
a single canonical shape.

<a id="collections"></a>
## Collections of DTOs

When a clump is really a *row* repeated N times (line items, reconciliation entries),
the DTO is the row and the parameter becomes a typed collection.

```php
/** @param Collection<int, CartLineItem> $items */
public function checkout(Cart $cart, Collection $items): Order
```

With spatie/laravel-data, use `DataCollection<CartLineItem>` for automatic casting
and serialization. With plain DTOs, a `Collection<int, CartLineItem>` plus a
`@param` annotation keeps PHPStan/Larastan happy. Avoid arrays of associative arrays
— that's the primitive obsession the DTO is meant to remove.

<a id="validation"></a>
## Preserving validation

The refactor must not silently drop validation. Trace where each clustered value was
validated before:

- If validation lived in a **FormRequest**, leave it there and build the DTO from the
  already-validated data. Don't duplicate rules into the DTO.
- If validation was **inline** (`if (! $x) throw ...`), move *invariant* checks into
  the DTO constructor and leave *input-shape* checks at the boundary.
- With spatie/laravel-data, `rules()` + `validateAndCreate()` can absorb boundary
  validation — but only move it there if you're replacing the FormRequest, not
  doubling it up.

After refactoring, the same invalid input must still be rejected at the same layer.
Add or keep a test that proves it.

<a id="location"></a>
## Where DTOs live

Follow the project. If there's already `app/Data/`, `app/DataTransferObjects/`, or a
domain-module layout (`app/Domain/Booking/Data/`), put it there. Otherwise default to
`app/Data/`, namespaced `App\Data`, and group by concept/domain once you have more
than a handful (`App\Data\Booking\…`).

<a id="not-candidates"></a>
## Things that are NOT DTO candidates

- **An existing Eloquent model already models the concept.** Passing `$user` beats
  inventing `UserData` carrying `$userId, $userEmail, $userName`. Only make a DTO when
  you specifically need a detached, immutable, partial, or serializable view.
- **A long list of genuinely independent options** with no recurring sub-cluster. That
  often signals a method doing too much — splitting the method may beat bagging the
  params. A DTO here just hides the smell.
- **Two parameters that only coincidentally appear together** in one place. A clump
  needs to recur (the scanner's `--min-occurrences`) *and* name a real concept.
- **Framework-mandated signatures** (controller `__invoke`, event listeners, job
  `handle()` injected dependencies) — don't reshape what the framework calls.
