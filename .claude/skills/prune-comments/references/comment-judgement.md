# Comment judgement — worked examples

Every example below is a block of four lines or more. Each shows the disposition, the
result, and — the part that matters — the reasoning that produced it. The pattern to
internalise: **read the code, find the fact, keep only the fact.**

---

## 1. PHP docblock restating the signature — REWRITE (7 → 2)

```php
/**
 * Get the bookings for a user.
 *
 * This method takes a user and returns their bookings. It accepts a User model
 * and an optional status filter, and returns a collection of Booking models.
 *
 * @param User $user The user
 * @param string|null $status The status
 * @return Collection The bookings
 */
public function bookingsFor(User $user, ?string $status = null): Collection
```

Everything here is in the signature. The `@param` and `@return` tags add nothing —
the parameters are typed, the return is typed. Nothing survives except, on reading
the body, the fact that it excludes cancelled bookings unless a status is given:

```php
/**
 * Cancelled bookings are excluded unless $status names them explicitly.
 */
```

If the body had held no such surprise, the whole block would be a **delete**.

---

## 2. PHP docblock whose tags carry real type information — KEEP

```php
/**
 * Group the rows by their category key.
 *
 * @param  array<int, array{id: int, category: string, total: float}>  $rows
 * @return array<string, list<array{id: int, total: float}>>
 */
public function groupByCategory(array $rows): array
```

Five lines, and it stays at five. PHP's `array` type says nothing; the array shapes
are the entire contract, and PHPStan reads them. Cut the prose line if you like — but
the tags are load-bearing and the block was never the problem.

---

## 3. Narration of the code below — DELETE (5 → 0)

```js
// First, we loop over each item in the cart.
// Then, for each item, we multiply the price by the quantity.
// We add that to a running total.
// Finally, we return the total.
const total = items.reduce((sum, i) => sum + i.price * i.quantity, 0);
```

A line-by-line English translation of one line of JavaScript. It cannot go stale
usefully — it can only go stale misleadingly. Delete it.

---

## 4. Preamble hiding one real fact — REWRITE (6 → 1)

```python
# This function handles the retry logic for the payment gateway.
# We need to be careful here because the gateway can be flaky.
# So what we do is retry the request a few times before giving up.
# The number of retries is set to 3.
# We also add a delay between retries.
# This should handle most transient failures.
```

"Retries three times with a delay" is visible in the code. The fact worth keeping is
the one detail the code can't explain — found by checking the gateway docs referenced
in the module:

```python
# Gateway returns 502 on cold start; three attempts covers the observed worst case.
```

---

## 5. Agent notice — DELETE (4 → 0)

```php
// NOTE FOR CLAUDE / AI ASSISTANTS:
// Do not modify the ordering of these array keys. The frontend depends on it.
// If you need to add a key, add it at the end.
// Always run the tests after touching this file.
```

Addressed to a tool, not a reader — but the second line is a genuine constraint about
the code. Keep the fact, drop the address:

```php
// Key order is part of the contract with the frontend; append only.
```

This is the common shape of an agent notice: a real constraint wrapped in
instructions to a machine. Extract, don't just delete.

---

## 6. Session residue — DELETE (4 → 0)

```js
// Updated as requested to use the new API endpoint.
// I've also refactored the error handling to be more robust.
// Let me know if you'd like me to adjust the retry behaviour.
// Step 2 of the migration is complete.
```

First-person narration of an edit, addressed to whoever was in that conversation. It
documents nothing about the code. Delete entirely; git records the change.

---

## 7. Commented-out code — DELETE (8 → 0)

```php
// public function oldCalculate(Order $order): float
// {
//     $total = 0;
//     foreach ($order->lines as $line) {
//         $total += $line->price;
//     }
//     return $total;
// }
```

Dead code kept "just in case". Git has it. Delete — and if the user wants a trail,
the answer is a commit message, not a mausoleum in the source file.

---

## 8. Generator scaffolding in a filled-in file — DELETE (5 → 0)

```php
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/
```

In `routes/web.php` — a file whose name and location say all of this. Delete.

The same banner in `config/database.php`, describing framework options, is
**out of scope for a general sweep** (see SKILL.md, Step 5): it's upstream text, and
editing it makes every framework upgrade diff against your changes. Offer it as its
own batch.

---

## 9. Load-bearing complexity — KEEP (6 stays 6)

```go
// Slots are allocated back-to-front deliberately. The scheduler in v2.3 assigns
// by lowest free index, so front-loading here causes it to interleave our jobs
// with the reporting queue's and both starve. Allocating from the end keeps the
// two runs contiguous. Upstream issue: scheduler#4471, fixed in v3 — revisit
// this when we upgrade.
// Regression test: TestSlotAllocationDoesNotInterleave.
```

Six lines, none removable. Every sentence carries a fact a reader cannot get from the
code: the version, the mechanism, the failure mode, the upstream issue, the test that
locks it, and the condition under which it can be deleted. **Report it as kept, with
that reasoning.** Cutting this to three lines would be vandalism dressed as tidying.

---

## 10. Vague and unverifiable — LEAVE, AND SAY SO

```js
// This needs to stay in this order.
// Moving it broke things last time.
// Not sure exactly why.
// Be careful.
```

Bad comment, real warning, no recoverable fact. You cannot shorten it without either
losing the warning or inventing a cause you don't know. Flag it in the report as
needing a human — ideally replaced by a test that fails when the order changes — and
leave it exactly as it is.

---

## The test to apply

For each line, ask: **could a competent reader get this from the code in ten
seconds?** If yes, it goes. If no, it stays — and the block is as long as the
remaining answers require, which is almost always one to three lines.
