# Worked example

Read this when you want a concrete, start-to-finish template for the workflow in
SKILL.md — including one case that should be refactored and two that should not. The
point is to show the *judgement*, not just the target code shape (for target shapes,
see `dto-patterns.md`).

## Contents
- [A clump that should become a DTO](#do)
- [A long list that should NOT become a DTO](#dont-split)
- [A clump an existing model already owns](#dont-model)

<a id="do"></a>
## A clump that should become a DTO

### Starting point

`BookingService` re-types the same start/end pair across three methods. The scanner run

```bash
python scripts/find_candidates.py app/Services/BookingService.php --min-occurrences 2
```

reports the strongest clump as `{$endDate, $startDate, $venueCode}` across 3 sites:

```php
final class BookingService
{
    public function createBooking(
        int $userId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $venueCode,
        int $partySize,
    ): Booking { /* ... */ }

    public function checkAvailability(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $venueCode,
    ): bool { /* ... */ }

    public function quotePrice(
        string $venueCode,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        int $partySize,
    ): Money { /* ... */ }
}
```

### The judgement call

The scanner clustered `start`, `end`, **and** `venueCode` because all three co-occur.
But the *concept* is just the date pair: a start and end that must be ordered and are
meaningless apart. `venueCode` travels alongside it but isn't part of it — it's a
separate axis. So the DTO is `DateRange` (start + end only); `venueCode` stays its own
argument. **This is the core skill: the scanner finds co-occurrence, you decide where
the concept's boundary actually is.** Over-including `venueCode` would produce a
muddled `BookingWindow`-ish blob that means less than `DateRange`.

Also note `quotePrice` lists its params in a different order (`venueCode` first) — a
clump that's re-ordered across call sites is exactly the wrong-argument-order bug a
DTO removes.

### Safety net first

```bash
./vendor/bin/pest tests/Unit/BookingServiceTest.php   # confirm green BEFORE touching code
```

If those methods aren't covered, write a characterization test pinning current
behaviour for one call site of each method before refactoring.

### The DTO

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
            throw new \InvalidArgumentException('Range end must not precede start.');
        }
    }

    public function nights(): int
    {
        return (int) $this->start->diff($this->end)->days;
    }
}
```

### Updated signatures

```php
public function createBooking(int $userId, DateRange $dates, string $venueCode, int $partySize): Booking
public function checkAvailability(DateRange $dates, string $venueCode): bool
public function quotePrice(string $venueCode, DateRange $dates, int $partySize): Money
```

### Updated call sites

Search the *whole* codebase for callers, not just the service —
`rg 'checkAvailability\(|createBooking\(|quotePrice\('`:

```php
// controller — before
$service->createBooking($request->user()->id, $start, $end, $venue, $party);
// after
$service->createBooking($request->user()->id, new DateRange($start, $end), $venue, $party);

// availability check — before
if ($service->checkAvailability($start, $end, $venue)) { /* ... */ }
// after
if ($service->checkAvailability(new DateRange($start, $end), $venue)) { /* ... */ }
```

Run the suite again after each concept. Green → done. The invalid-range case is now
enforced in one place (the constructor) instead of being re-checked, or forgotten, at
each call site — add a test asserting `new DateRange($end, $start)` throws.

<a id="dont-split"></a>
## A long list that should NOT become a DTO (split instead)

```php
public function exportReport(
    DateTimeImmutable $from,
    DateTimeImmutable $to,
    string $format,
    bool $includeCharts,
    bool $compress,
    string $recipientEmail,
): void
```

Six parameters, so the scanner's long-list check flags it. But ask the naming litmus:
what *one concept* do these share? There isn't one. `from`/`to` is a `DateRange` (and if
that range recurs elsewhere, extract it — but only the pair). The rest —
`format`, `includeCharts`, `compress`, `recipientEmail` — are independent *options*
plus a *delivery target*. Bagging them into `ExportReportParams` just renames the smell:
the real problem is the method does two jobs (build a report, then deliver it).

Better fix: split responsibilities, and apply the `DateRange` extraction only:

```php
public function buildReport(DateRange $period, ReportFormat $format): Report
public function deliver(Report $report, string $recipientEmail, bool $compress): void
```

`ReportFormat` here is an enum (the format, with charts arguably part of it), not a DTO.
The lesson: a long list is a prompt to ask "is there a concept, or a method doing too
much?" — and only sometimes is the answer a DTO.

<a id="dont-model"></a>
## A clump an existing model already owns

```php
public function notifyMember(int $userId, string $userEmail, string $userName): void
```

`{userId, userEmail, userName}` recurs across the notification methods, so the scanner
flags it. But these three are just fields of a `User` the caller already has. Inventing
`MemberContact` to carry them is a near-duplicate of the model with none of its
behaviour. Pass the model:

```php
public function notifyMember(User $member): void
```

Only invent a DTO here if you specifically need a *detached, immutable, or partial* view
— e.g. notifying an address that has no `User` row yet, or deliberately narrowing what
the notifier can see. Absent that need, the model is the concept.
